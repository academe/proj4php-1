[![Build Status](https://img.shields.io/travis/proj4php/proj4php/master.svg)](https://travis-ci.org/proj4php/proj4php)
[![Latest Stable Version](https://img.shields.io/packagist/dt/proj4php/proj4php.svg)](https://packagist.org/packages/proj4php/proj4php)

# proj4php
PHP-class for proj4
This is a PHP-Class for geographic coordinates transformation using proj4 definitions,
thanks to a translation from Proj4JS. 

# New Developments

This example will help to put the architecture into context:

```php
<?php

include 'vendor/autoload.php';

use proj4php\Point\Geocentric;
use proj4php\Point\Geodetic;
use proj4php\Projection\Lcc;
use proj4php\Ellipsoid;
use proj4php\Datum;

echo "<pre>";

// Datum for the point.
$sphere = Ellipsoid::fromAB(6370997.0, 6370997.0, 'sphere', 'Normal Sphere (r=6370997)');
$datum_s = new Datum($sphere);

// Ellipsoid for the projection.
$ellipsoid = new Ellipsoid(6378137.0, 298.257222101, 'GRS80', 'GRS 1980 (IUGG, 1980)');

// Create the projection.
// Example ETRS89 (lcc):
// +proj=lcc +lat_1=35 +lat_2=65 +lat_0=52 +lon_0=10 +x_0=4000000 +y_0=2800000 +ellps=GRS80 +units=m +no_defs 
// We pass in a and b here, but really should pass in the ellipsoid or datum.
$projection = new Lcc([
    'a' => $ellipsoid>getA(),
    'b' => $ellipsoid>getB(),
    'lat_0' => 52,
    'lat_1' => 35.0,
    'lat_2' => 65.0,
    'lon_0' => 10.0,
    'x_0' => 4000000.0,
    'y_0' => 2800000.0,
]);

// Point at Edinburgh with default WGS84 datum.
echo "Starting geodetic point (Edinburgh, GRS80 sphere):\n";
$point = new Geodetic([55.953251, -3.188267], $datum_s);
var_dump($point->toArray());

// Shift datum of point? See notes below.
//$point = $point->shiftDatum(new Datum($ellipsoid));

// Convert the geodetic point to an xy point.
echo "Converted to ETRS89 (lcc):\n";
$xy = $projection->forward($point);
var_dump($xy);

// Convert the xy point back to a geodetic point.
echo "Converted back to geodetic:\n";
$point2 = $projection->inverse($xy);
var_dump($point2->toArray());
```

Note that there is no XY point class yet, so `$xy` is just an array in this example.
That class will be created next, after some refactoring in Lcc.php.

Note also that the projection here is definend with a GRS80 ellipsoid, but the geodetic point
uses a spherical datum. So to bring these into line, the point
needs a datum shift. The projection is defined just with an ellipsoid and not a full datum
(with centre-shift parameters) so *I think* this is just an ellipsoid conversion needed
here. The geodetic point will need its lat/long/height shifted to the new ellipsoid
(from the sphere) before the `forward` translation is performed. If the final XY point needs
to be in any other datum or using any otehr ellipsoid, then another shift is needed.
This can get confusing, which is why every point of any type must carry its datum with
it, and the `forward`/`inverse` will know if any initial shifts are needed when they
operate.

The result is shown below, which is *near enough* (given no ellipsoid conversions are
done) correct when checked online:

```
Starting geodetic point (Edinburgh, GRS80 sphere):
array(3) {
  ["lat"]=>
  float(55.953251)
  ["long"]=>
  float(-3.188267)
  ["height"]=>
  float(0)
}
Converted to ETRS89 (lcc):
array(2) {
  ["x"]=>
  float(3208188.1979678)
  ["y"]=>
  float(3296012.7387914)
}
Converted back to geodetic:
array(3) {
  ["lat"]=>
  float(55.953251)
  ["long"]=>
  float(-3.188267)
  ["height"]=>
  float(0)
}
```

----

A [legacy branch php4proj5.2](https://github.com/proj4php/proj4php/tree/proj4php5.2) will be
maintained for older applications that need it.

## Using

```php
// Use a PSR-4 autoloader for the `proj4php` root namespace.
include 'vendor/autoload.php';

use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;

// Initialise Proj4
$proj4 = new Proj4php();

// Create two different projections.
$projL93    = new Proj('EPSG:2154', $proj4);
$projWGS84  = new Proj('EPSG:4326', $proj4);

// Create a point.
$pointSrc = new Point(652709.401, 6859290.946, $projL93);
echo "Source: " . $pointSrc->toShortString() . " in L93 <br>";

// Transform the point between datums.
$pointDest = $proj4->transform($projWGS84, $pointSrc);
echo "Conversion: " . $pointDest->toShortString() . " in WGS84<br><br>";

// Source: 652709.401 6859290.946 in L93
// Conversion: 2.3557811127971 48.831938054369 in WGS84
```

There are also ways to define inline projections.
Check http://spatialreference.org/ref/epsg/ and seek for your projection and proj4 or OGC WKT definitions.

Add a new projection from proj4 definition with a name :
```php
// add it to proj4
$proj4->addDef("EPSG:27700",'+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs');

// then Create your projections
$projOSGB36 = new Proj('EPSG:27700',$proj4);
```

Or without a name :
```php
// Create your projection
$projOSGB36 = new Proj('+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs',$proj4);
```

You can also create your projection from OGC WKT definition :
```php
$projOSGB36 = new Proj('PROJCS["OSGB 1936 / British National Grid",GEOGCS["OSGB 1936",DATUM["OSGB_1936",SPHEROID["Airy 1830",6377563.396,299.3249646,AUTHORITY["EPSG","7001"]],AUTHORITY["EPSG","6277"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4277"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],PROJECTION["Transverse_Mercator"],PARAMETER["latitude_of_origin",49],PARAMETER["central_meridian",-2],PARAMETER["scale_factor",0.9996012717],PARAMETER["false_easting",400000],PARAMETER["false_northing",-100000],AUTHORITY["EPSG","27700"],AXIS["Easting",EAST],AXIS["Northing",NORTH]]',$proj4);
```

## Points

### Geocentric

A geocentric point can be created with various format parameters:

    $geocentric = new \proj4php\Point\Geocentric($coords, $datum);

The `$coords` can be formatted as a comma or space separated string,
as an associative array, or as a numeric array. For example:

    "123,456,789"
    "123 456 789"
    [123, 456, 789]
    ['y' => 456, 'x' => 123, 'z' => 789]

With the exception of the associative array (which can be in any order),
the order of the ordinates will be x, y, z.

## Developing - How to contribute

Feel free to fork us and submit your changes!

## OSGeo community project

![ScreenShot](https://wiki.osgeo.org/images/8/80/OSGeo_community.png)

Proj4php is also an OSGeo community project. See [here](https://wiki.osgeo.org/wiki/OSGeo_Community_Projects) for further details.
