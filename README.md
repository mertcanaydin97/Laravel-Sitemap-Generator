# Laravel Sitemap Generator Helper

Simple laravel sitemap generation.

## Installation

Create 'Helpers' directory inside 'app' folder.
Download and move 'SitemapHelper.php' to 'Helpers' folder.
After copy open 'config/app' directory and add line below to 'aliases' array.

```php
'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        'MailHelper' => App\Helpers\MailHelper::class,
        'SitemapHelper' => App\Helpers\SitemapHelper::class,
    ])->toArray()
```

## Usage
You can use this helper two different ways. First you need to import SitemapHelper in 'routes/web.php' and then add 'sitemapcrawl' middleware to routes that you want to add sitemap.
Example
```php
use App\Helpers\SitemapHelper;

Route::group(['middleware' => 'sitemapcrawl'], function () {

    Route::get('/', [App\Http\Controllers\MainController::class, 'index'])->name('index');

    Route::get('/category/{cat}', [App\Http\Controllers\MainController::class, 'portfolios'])->name('portfolio_category');
    Route::get('/portfolio/{cat}/{slug}', [App\Http\Controllers\MainController::class, 'portfolio'])->name('portfolio_single');

});  

#Casting Sitemap#

Route::get('/sitemap.xml', function () {
    $sitemap = new SitemapHelper();

    $map = $sitemap->setRoute('portfolio_category');
    $map = $sitemap->setRouteData([
        'key' => 'cat',#param of given route#
        'pluck' => 'slug',#Column for find model data#
        'model' => 'App\PortfolioCategory',#Model full path#
        'date_key' => 'updated_at',#Last modify date#
    ]);
    #if you have relation data and you want to add like subpage try code below#
    $child = $map->setRouteRelation('portfolio');#Relation name of model#
    $child = $child->setRelationData([
        'keys' => ['parent' => 'cat', 'child' => 'slug'],#You need to add all params#
        'route_name' => 'portfolio_single',
        'key' => 'slug',
        'pluck' => 'slug',
        'date_key' => 'updated_at'
    ]);
    #You can add unlimited child data.#
    $childs = $child->setRouteRelation('portfolio');
    $childs = $childs->setRelationData([
        'keys' => ['parent' => 'cat', 'child' => 'slug'],
        'route_name' => 'portfolio_single',
        'key' => 'slug',
        'pluck' => 'slug',
        'date_key' => 'updated_at'
    ]);

    return $sitemap->render();


})->name('sitemap');
