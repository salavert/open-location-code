Open Location Codes
=======================

PHP port of Google Open Location Codes.

Open Location Codes are short, generated codes that can be used like street addresses, for places where street addresses don't exist.

http://openlocationcode.com

Open Location Codes were developed at Google's Zurich engineering office, and then open sourced so that they can be freely used.

Usage
-----

Encode latitude and longitude to obtain a code

```php
$latitude = 51.3701125;
$longitude = -1.217765625;
$olc = new OpenLocationCodes();
$openLocationCode = $olc->encode($latitude, $longitude); // Will return 9C3W9QCJ+2V
```

Original code by Google
-----------------------

https://github.com/google/open-location-code