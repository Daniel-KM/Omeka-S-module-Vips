Vips thumbnailer (module for Omeka S)
=====================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__


[Vips thumbnailer] is a module for [Omeka S] that integrates [Vips], a library
specialized in the creation of thumbnails. Its main qualities are to be very
quick (5 to 10 times quicker than [GD] and [ImageMagick]) and to be memory
efficient, as the [ad says]. So it is ideal for small servers, and for big ones
of course: this is the [thumbnailer used by Wikipedia].

It has another interesting feature too: the possibility to crop the square
thumbnail according to the point of attention, that may not be the center
(gravity).

Furthermore, a bash or php script is provided to create all thumbnail in bulk
from the command line very quickly.

This module requires a package installed on the server that is less common than
ImageMagick or GD, but provided natively by all main linux distributions: [vips].


Installation
------------

See general end user documentation for [installing a module].

The module manages the calls to vips, so vips must be installed on the server.

### Module

The module can work in two modes:
- Php library mode (recommended): uses the composer library [jcupitt/vips] for
  best performance. The library v1 requires the php extension ext-vips, the
  library v2 requires ext-ffi.
- Cli mode: uses the `vips` command-line tool directly, without any php
  extension or composer dependency.

The module automatically detects the available mode and selects the best one.
If both are available, the PHP library mode is preferred.

* From the zip

Download the last release [Vips.zip] from the list of releases and uncompress it
in the `modules` directory. The zip works in cli mode out of the box. For PHP
library mode, run `composer require jcupitt/vips:^1.0` (or `^2.0`) inside the
module directory after extraction.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Vips`.

For PHP library mode (recommended), go to the root of the module and run:

```sh
# v1 (requires ext-vips):
composer require jcupitt/vips:^1.0 --no-dev
# or v2 (requires ext-ffi with ffi.enable=true in php.ini, less secure):
composer require jcupitt/vips:^2.0 --no-dev
```

For CLI mode, no composer dependency is needed: the module uses the `vips`
command-line tool directly. See below [section Vips](#vips).

Then install it like any other Omeka module and follow the config instructions.

* For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Vips/phpunit.xml --testdox
```

**Note**: Some tests may be skipped according to the mode of install (with
php-vips or cli vips).

### Vips

The library [vips] must be installed on the server.

There are three ways to use it, depending on the mode:

- the command line tool libvips, a simple package available in all linux
  distributions.
- the php library jcupitt/vips (v1) with ext-vips. This is recommended for the
  speed (two times quicker), but in the rare cases where there are big images
  that require more memory than the php one, the cli tool should be used.
- the php library jcupitt/vips (v2) with ext-ffi. The new version of the library
  requires only the extension `ffi` enabled on the server. The performance is
  the same than v1.

There are two versions of the library [jcupitt/vips]. Both have the same API and
are compatible with this module. The module automatically detects the installed
version.

- v1: requires the php extension `ext-vips` (installed as system package or via
  pecl, see below). Simpler to set up. **Recommended for production.**
- v2: requires the php extension `ext-ffi` with `ffi.enable=true` in php.ini
  (not `preload`), and another key for php 8.3+ (see below). See [php doc on ffi]
  for more information. It avoids the need for a custom php extension, but
  **`ffi.enable=true` has security implications**: FFI allows PHP to call any C
  function and access memory directly, bypassing `disable_functions` and `open_basedir` restrictions. The PHP documentation [recommends against enabling FFI globally in production].
  So use v1 or cli mode on sensitive servers.

#### As standard package for cli

To install the cli tool on Debian/Ubuntu, just run this command, with option
"--no-install-recommends" to avoid to install the heavy and useless graphical
interface:

```sh
sudo apt install --no-install-recommends libvips-tools
```

or for on Centos/RedHat:

```sh
sudo dnf install vips-tools
```

Recommanded version is 8.10 or higher. Versions prior to 8.4 have not been
tested. 8.16 or higher supports jpeg2000.

#### As standard php extension with jcupitt/vips v1 (php-vips)

The extension php-vips is only needed for jcupitt/vips v1. It can be installed
in two ways, depending on distribution: via package or pecl.

##### php-vips via package

To install the php extension [php-vips] on Debian/Ubuntu, just run this command,
with option "--no-install-recommends" to avoid to install the heavy and useless
graphical interface:

```sh
sudo apt install --no-install-recommends php-vips
```

or for on Centos/RedHat:

```sh
sudo dnf install php-vips
```

##### php-vips via pecl

If the php-vips package is not available for your distribution, or if you need a
more recent version, you can install it via [pecl].

For Debian/Ubuntu:

```sh
# Install the libvips development files and the PHP development tools.
sudo apt install --no-install-recommends libvips-dev php-dev php-pear
# install the extension via pecl.
sudo pecl install vips
# Enable the extension with a dedicated ini (adapt it for your php version).
echo "extension=vips.so" | sudo tee /etc/php/8.1/mods-available/vips.ini
sudo phpenmod vips
```

For Centos/RedHat:

```sh
# Install the libvips development files and the PHP development tools.
sudo dnf install vips-devel php-devel php-pear
# install the extension via pecl.
sudo pecl install vips
# Enable the extension with a dedicated ini (adapt it for your php config).
echo "extension=vips.so" | sudo tee /etc/php.d/20-vips.ini
```

Finally, restart the web server of php-fpm:

```sh
# Either Apache:
sudo systemctl restart apache2
# or php-fpm:
sudo systemctl restart php8.1-fpm
```

Check the installation with:

```sh
php -m | grep vips
```

#### As standard php extension with jcupitt/vips v2 (with php-ffi enabled)

For jcupitt/vips v2, only `ext-ffi` is required, which is standard since php 7.4,
but it requires the setting `ffi.enable=true` in php.ini, that is not enabled by
default.

Furthermore, for php 8.3+, the key `zend.max_allowed_stack_size=-1` should be
added to php.ini.

### Vips as default thumbnailer

When the module is enabled, the [default thumbnailer] is automatically set to
Vips when the library jcupitt/vips is available (with ext-vips or ext-ffi), or
VipsCli otherwise.

The main interest to use Vips as thumbnailer is not only the performance, but
the possibility to center on the region of interest when cropping the image to
get the square thumbnails. Just set it in the file "config/local.config.php" at
the root of Omeka:

```php
    'thumbnails' => [
        'types' => [
            'square' => [
                'options' => [
                    // Other options: low, centre, high, attention, entropy, depending on version of vips.
                    'vips_gravity' => 'attention',
                ],
            ],
        ],
        'thumbnailer_options' => [
            // Set directory path of "vips" if not in the system path.
            'vips_dir' => null,
        ],
    ],
    'service_manager' => [
        'aliases' => [
            // Automatically set by the module (or VipsCli if the library jcupitt/vips is unavailable).
            'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\Vips',
        ],
    ],
```


### Create all thumbnails from the command lines

From the root of Omeka, run:
```sh
# php
php modules/Vips/data/scripts/thumbnailize.php
# or bash
bash modules/Vips/data/scripts/thumbnailize.sh
```

All arguments are provided: all files or only missing ones, in parallel or not,
with a specific crop mode, etc.


TODO / Bugs
-----------

- [x] Use the tiled images when available for arbitrary size request (ok for vips/tiled tiff).
- [x] Add a processor for [php-vips].
- [x] Use vips as Omeka thumbnailer.
- [ ] Add auto as default type of tiles (so choose tiled tiff if vips is installed, etc.).
- [ ] Use the library [OpenJpeg] ("libopenjp2-tools" on Debian, or "openjpeg" on Centos instead of ImageMagick for a [performance] reason: ImageMagick always open the file as a whole even when extracting a small part.
- [ ] Fix bitonal with vips.
- [ ] Fix save jp2 with vips/convert.
- [ ] Add an auto choice for thumbnailer (and select it according to input format) and tile type.
- [ ] Manage icc profile.
- [ ] Manage option "autoOrient"
- [ ] Manage option "pdfUseCropBox".


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

### Module

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.

This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.

### Libraries

The module uses [vips] and library [jcupitt/vips]. See their licence on the site.


Copyright
---------

* Copyright Daniel Berthereau, 2020-2026 (see [Daniel-KM])


[Vips thumbnailer]: https://gitlab.com/Daniel-KM/Omeka-S-module-Vips
[Omeka S]: https://omeka.org/s
[ad says]: https://github.com/libvips/libvips/wiki/Speed-and-memory-use
[vips]: https://libvips.github.io/libvips
[GD]: https://secure.php.net/manual/en/book.image.php
[ImageMagick]: https://www.imagemagick.org
[thumbnailer used by Wikipedia]: https://www.mediawiki.org/wiki/Extension:VipsScaler
[jcupitt/vips]: https://packagist.org/packages/jcupitt/vips
[php doc on ffi]: https://www.php.net/manual/en/ffi.configuration.php
[recommends against enabling FFI globally in production]: https://www.php.net/manual/en/ffi.intro.php
[php-vips]: https://github.com/libvips/php-vips
[pecl]: https://pecl.php.net/package/vips
[Vips.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Vips/-/releases
[default thumbnailer]: https://omeka.org/s/docs/user-manual/configuration/#thumbnails
[OpenJpeg]: https://github.com/uclouvain/openjpeg
[performance]: https://cantaloupe-project.github.io/manual/4.0/images.html
[libvips]: https://libvips.github.io/libvips
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Vips/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[from Gimp]: https://pippin.gimp.org/sRGBz
[Universal Viewer plugin for Omeka Classic]: https://gitlab.com/Daniel-KM/Omeka-plugin-UniversalViewer
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
