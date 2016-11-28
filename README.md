[![Build Status](https://travis-ci.org/ahelhot/moredraw.svg?branch=master)](https://travis-ci.org/ahelhot/moredraw)

# MoreDraw
Smart handlebars template with two-side render (php/js) with just one template and reactive data update.
Based on zordius/lightncandy && handlebars parser

#### Require (install)
- composer (for install)
- php7 (at least)
- http://handlebarsjs.com/ (optional, for js render only)

### Install PHP Side

install deps:
```
$ cd /your-project/
$ composer require ahelhot/moredraw
```
in project init (config, etc..) (before we use template render)

```
require_once 'vendor/autoload.php';

$moreDraw = new NeoHandlebars\MoreDraw();
$moreDraw->init([
  'templates_dir' => __DIR__ . '/templates', // where templates will be
  'cache_dir' => __DIR__ . '/tmp/cache' // where cache will be
]);
```

### Install JS Side (optional, only for js use)

Install handlebars parser (handlebars.min-latest.js) from this page:
http://builds.handlebarsjs.com.s3.amazonaws.com/bucket-listing.html?sort=lastmod&sortdir=desc

Or from official site:
http://handlebarsjs.com/installation.html


in site footer (place when you include js files), add js lib

```
<!-- include official handlebars parser lib -->
<script src="/vendor/handlebars.min-latest.js"></script>

<!-- include moreDraw -->
<div style="display: none;">
  <!-- moredraw raw templates and partials -->
  <?=$moreDraw->getAllJSTemplates()?>
</div>

<script src="/moredraw/handlebars.js"></script>
```

### Use 

##### Use. Server Side

Create file **examples/test.hbs** in your templates folder. With some content like this: **hello {{name}}!**

```
// _index is optional, only if we need provided data in js later
echo $moreDraw->render('examples/test', ['_index' => 'to_world', 'name' => 'world']); // Hello world!
echo $moreDraw->render('examples/test', ['_index' => 'to_universe', 'name' => 'universe']); // Hello universe!
```

##### Use. Client Side

```
let data = MoreDraw.getData('examples/test', 'to_world'); // {'name' => 'world'}
data.name = data.name + " & me";
let html = MoreDraw.render('examples/test', data); // Hello world & me!
```

