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

This module requires a package installed on the server that is less common than
ImageMagick or GD, but provided natively by all main linux distributions: [vips].


Installation
------------

See general end user documentation for [installing a module].

### Module

The module uses an external library, [jcupitt/vips], so use the release zip
to install it, or use and init the source.

* From the zip

Download the last release [Vips.zip] from the list of releases (the "master"
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Vips`, go to the root module, and run:

```sh
composer install --no-dev
```

**Important**: if you don't have php-vips installed but only the package for
cli vips, don't run this command, because it will fail. The module don't need
the composer packages to run vips via the cli.

Then install it like any other Omeka module and follow the config instructions.

### Note on the version of the library jcupitt/vips

There are two version of the library [jcupitt/vips]. Since version 2, the
library requires a specific configuration in php.ini: ffi must be enabled
globally. See [php doc on ffi] for more  information. Furthermore, for php 8.3,
the key `zend.max_allowed_stack_size=-1` should be added to the php.ini.

So the module integrates the last version of the branch 1, but you can update
composer and use version 2 if your environnment and php.ini are ready and if you
still need more performance (undetermined).

### Vips

The library [vips] must be installed on the server.

Two thumbnailers can be installed: the command line tool libvips or the php
extension php-vips. This extension is recommended for the speed (two times
quicker), but in the rare cases where there are big images that require more
memory than the php one, the cli tool should be used. The php extension is a
recent development that may not be available on old linux distributions.

#### As php extension

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

#### As cli

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
tested.


### Vips as default thumbnailer

When the module is enabled, the [default thumbnailer] is automatically set to
Vips, when the extension php-vips is available, or VipsCli.

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
            // Automatically set by the module (or VipsCli if php extension php-vips is unavailable).
            'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\Vips',
        ],
    ],
```


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

* Copyright Daniel Berthereau, 2020-2025 (see [Daniel-KM])


[Vips thumbnailer]: https://gitlab.com/Daniel-KM/Omeka-S-module-Vips
[Omeka S]: https://omeka.org/s
[ad says]: https://github.com/libvips/libvips/wiki/Speed-and-memory-use
[vips]: https://libvips.github.io/libvips
[GD]: https://secure.php.net/manual/en/book.image.php
[ImageMagick]: https://www.imagemagick.org
[thumbnailer used by Wikipedia]: https://www.mediawiki.org/wiki/Extension:VipsScaler
[jcupitt/vips]: https://packagist.org/packages/jcupitt/vips
[php doc on ffi]: https://www.php.net/manual/en/ffi.configuration.php
[php-vips]: https://github.com/libvips/php-vips
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
