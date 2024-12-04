<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Route;
use Exception;
use DOMDocument;
use Carbon\Carbon;
use SimpleXMLElement;
class SitemapHelper
{
    private $url;
    private $route;
    private $route_name;
    private $child;
    public function __construct($route = null, $route_name = null, $child = null)
    {
        $this->route = $route;
        $this->route_name = $route_name;
        $this->child = $child;
    }
    public static function generateResource($routedata)
    {
        $routeCollection = Route::getRoutes();
        $moddate = strtotime(date('Y-m-d H:i:s'));
        $new_data = [];
        foreach ($routeCollection as $r) {
            if ((count($r->parameterNames()) == 0) && in_array('sitemapcrawl', $r->gatherMiddleware())) {
                $new_data[route($r->getName())] = $moddate;
            }
        }
        $all_urls = $new_data + self::generateLinks($routedata);
        return self::writeSitemap($all_urls);

    }
    protected static function generateLinks($data)
    {
        $new_data = [];
        $childs = [];

        foreach ($data as $d => $link) {
            $route = [];
            $slug_columns = [];
            $model = app($link['model']);
            $slugs = $model->pluck($link['pluck'])->toArray();
            $dates = $model->pluck($link['date_key'])->toArray();
            $mcount = count($slugs);

            if (count($slugs) > 0) {


                for ($i = 0; $i < $mcount; $i++) {

                    $route = route(
                        $d,
                        [$link['key'] => $slugs[$i]]
                    );

                    if (isset($link['relations'])) {
                        $childs = self::generateChilds($link['relations'], $link['model'], $slugs[$i]);
                    }
                    $new_data[$route] = strtotime($dates[$i]);

                }

            }


        }
        return $new_data + $childs;
    }

    protected static function generateChilds($data, $model, $parent_slug)
    {
        $new_data = [];
        $childs = [];
        foreach ($data as $r => $relation) {

            $rel_data = $r;
            $model = app($model);
            $child = $model->with($r)->first()->{$r};
            $relation_slugs = $child->pluck($relation['pluck'])->toArray();
            $relation_dates = $child->pluck($relation['date_key'])->toArray();
            $rcount = count($relation_slugs);

            if ($rcount > 0) {
                for ($c = 0; $c < $rcount; $c++) {

                    if (isset($data[$rel_data]['keys']['child'])) {
                        $params[$c][$data[$rel_data]['keys']['parent']] = $parent_slug;
                        $params[$c][$data[$rel_data]['keys']['child']] = $relation_slugs[$c];
                    } else {
                        $params[$c][$data[$rel_data]['keys']['parent']] = $relation_slugs[$c];
                    }
                    $route = route(
                        $data[$rel_data]['route_name'],
                        $params[$c]
                    );
                    if (isset($relation['relations'])) {
                        $childs = self::generateChilds($relation['relations'], $relation['model'], $relation_slugs[$c]);
                    }
                    $new_data[$route] = strtotime($relation_dates[$c]);
                }
            }
        }


        return $new_data + $childs;
    }

    protected static function writeSitemap($resources)
    {
        // Prepare XML
        $urlset = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="https://www.sitemaps.org/schemas/sitemap/0.9 https://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"></urlset>');

        // Add all resources in
        foreach ($resources as $url => $lastmod) {
            // Ensure the lastmod has a timezone by parsing and writing it out again
            $lastmod = Carbon::parse($lastmod);
            if ($lastmod->tzName === 'UTC' && date_default_timezone_get() != null) {
                $lastmod->shiftTimezone(date_default_timezone_get());
            }

            // Add the node
            $entry = $urlset->addChild('url');
            $entry->addChild('loc', $url);
            $entry->addChild('lastmod', $lastmod->format('Y-m-d\TH:i:sP'));
            $entry->addChild('priority', str_replace(',', '.', round((1 - .05 * Substr_count($url, '/')), 1)));
            $entry->addChild('changefreq', 'monthly');
        }


        return response($urlset->asXML(), 200)->header('Content-Type', 'application/xml');
    }
    public function render()
    {
        return self::generateResource($this->route);
    }

    public function setRoute($route)
    {
        $this->route_name = $route;
        return $this;
    }
    public function setRouteData($data)
    {
        $this->route[$this->route_name] = $data;
        return $this;
    }

    public function setRouteRelation($route)
    {
        $this->child = $route;
        return $this;
    }
    public function setRelationData($data)
    {
        $this->route[$this->route_name]['relations'][$this->child] = $data;
        return $this;
    }


}
