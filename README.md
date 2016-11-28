# MoreDraw
Smart handlebars template with two-side render (php/js) with just one template and reactive data update.
Based on zordius/lightncandy && handlebars parser

#### Require (install)
- composer
- php7

### Install

install deps:
```
$ cd /your-project/
$ composer require ahelhot/moredraw
$ composer install
```
in project init (config, etc..) (before we use template render)

```
require_once(__DIR__ . '/vendor/autoload.php');

$MoreDraw = new \NeoHandlebars\MoreDraw();
$MoreDraw->init();
```

