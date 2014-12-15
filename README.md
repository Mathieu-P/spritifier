Index:
======
0- Example

1- What is Spritifier?

2- Necessary sprite definition constraints

3- How to generate or regenerate the image sprite?

4- How to add a new icon in the sprite?

5- How to handle complex css selectors?

6- How to enhance the sprite?


0- Example
==========

- Request `spritifier-ui.php` through a web server that can serve PHP 5 files.

- Choose a configuration file (userConf-default.php is ok).

- Enter/change the `sprite width`

- Click the `Refresh` green button to preview the changes, image size and lost
pixel ratio (smaller the better). Test other sprite widths if you want.

- When ready, click the `Export` button. The sprite image and sprite css are
generated in the /tmp directory of the web server (write right required).

- This process can be automatised using `spritifier-cli.php` in command line
with configuration provided in a `userConf-*.php` file.


1- What is Spritifier?
======================
Spritifier offers a simplified process to generate an image sprite. A sprite
is useful to reduce the number of http requests needed for your images. So,
the website will load more quickly.

Through Spritifier, the sprite generation process can become part a continuous
integration system.

How does it work? Spritifier simply takes a css file which defines the images
(or icons preferably) that are used in your website. It then compiles them to
a new single image (the sprite) with all your icons and a new css file
describing the sprite.

Spritifier can handle multiple image resolutions, which is particularly handy
for mobile device optimization. If so, a sprite is created for each
registered resolution.


2- Necessary sprite definition constraints
==========================================
To generate the image sprite, Spritifier reads a css file which defines all
the icons that have to be put inside the sprite. This file is a classic css
file, but it must not contain too much or too less information for Spritifier
to make a good work. We call this file the "(css) definition file".

Since it is a good idea (for tests and evolutions) to easily be able to
switch the website between sprited version and non-sprited version, there must
be some relation between the sprite and how the icons are rendered into the
site. The generated sprite image will always be used as a background image. As a
consequence, it is necessary to define all the independent icons as a background.

The simplest manner to achieve this is by writting such css rules:
```
.red_icon {
	/* for ie6, use something else, such as display:block; float:left; */
	display:inline-block;
	width:20px;
	height:20px;
	background:url(img/red_icon.png) no-repeat scroll 0 0;
}
```

Then, to display the image inside your website, use a code such as:
```
<span class="red_icon"></span>
```
Note: Do not use anymore `<img src="img/red_icon.png" width="20" height="20" />`

Multiple image resolutions can be handled to generate multiple sprites. For an
icon being available in several resolutions, you must add a specific css rule:
`-spritifier-dpi: @2x`
This means the image is available in base resolution and @2x resolution. The
resolution @2x is defined as a ratio of the base resolution and must be
indicated in the configuration file.

Example for an image that should be available in base resolution, @2x and @4x
resolutions:
```
.red_icon {
  /* for ie6, use something else, such as display:block; float:left; */
  display:inline-block;
  width:20px;
  height:20px;
  background:url(img/red_icon.png) no-repeat scroll 0 0;
  -spritifier-dpi: @2x @4x;
}
```

Spritifier can also parse less files (http://lesscss.org/) using the
".icon" mixin, that is rules written:
```
.blue_icon {
  .icon("/img/blue_icon.png", 128px, 40px);
}
```
Such rules automatically create a base resolution and an @2x resolution.

To use Spritifier, you may have to spend 1 hour or 2 to rewrite how your images
are rendered. But (1) the gain will be quickly noticeable, (2) you'll never have
to do it again, (3) you don't have to do it all at the same time (Spritifier
make it easy for the sprite to be changed afterwards).

So, the better is to create a `spritifier_definition.css` file which contains
all the image definitions. By running Spritifier on this file, you'll be able
to create a `spritifier_generated.css` file. Then you can switch from the
definition file to the generated file as you wish.
To keep both files and switch from one to the other, you can create a symbolic
link `spritifier.css` that links to one or the other file.


3- How to (re)generate the image sprite?
========================================

3-a- Web interface
------------------
In a web browser, browse to `/path/to/spritifier-ui.php`
If all is correctly configured, the list of images to insert in the sprite
should already be visible on the left side. Otherwise, it's time to configure
Spritifier (go to 3-c).

Select the right configuration file used by your project. Then you can adjust
some sprite generation parameters (width, compaction method).

When done, click the Export button. The new sprite and associated css will
be created according to configuration instructions. Be sure your web server
can write in these directories (default to `/tmp directory`).

If you used the same file names and followed recommendations found in
paragraph 2, you may have to update the `spritifier.css` symbolic link
to `spritifier_generated.css`.
Now refresh your website, the newly created sprite should be used.

If you encounter issues during sprite generation:
- check the configuration file
- check your directories are writable
If you encounter issues because the sprite is not used:
- check the http requests to find 404 errors
If The sprite is loaded but is all messed up:
- It is often the cause of a browser cache issue. Start by clearing your browser
 cache. If the sprite now appears correctly, you know the problem. Take the
necessary actions (you are a web developer, you know how to solve that). The
`SPRITE_VERSION` constant could become handy.


3-b- Command line
-----------------
The Spritifier is available through the Spritifier command if installed
(`make install`), or via the `spritifier-cli.php`
(command line `php5 spritifier-cli.php`).
Check usage notes for all the available parameters.


3-c- Configuration
------------------
In the install directory of Spritifier, locate `userConf-default.php`.
This file defines a php variable `$spf_conf` which is a hash table.
Keys of this hash table are configuration names. The associated value is an
other hash table for configuration instructions.

When using Spritifier, it is recommended to duplicate this file and use format
`userConf-[projectname].php`. By doing so, it will be easy to switch the
configuration in `spritifier-ui.php`. For `spritifier-cli.php`, the configuration
file can be provided as a parameter.

`cssInput`: the path to `spritifier_definition.css`. Use absolute path preferably.

`cssOutput`: the path to `spritifier_generated.css`. Use absolute path preferably.

`imgRootPath`: the root path for the images defined in `spritifier_definition.css`
For instance if the file path for your image is `"/path/to/www/img/red_icon.png"`
and your css file contains `background:url(img/red_icon.png)`
then `imgRootPath` have to be `"/path/to/www/"`

`pngOutput`: the path for the generated sprite image (example: `"spritifier.png"`).
Use absolute path preferably.

`pngWebUrl`: the url your webserver will use to serve `spritifier.png`
For instance, if the generated png file is `/path/to/www/img/spritifier.png`
And your webserver root is `/path/to/www/`
Then `pngWebUrl` have to be `"/img/spritifier.png"`.
To handle cache issues when updating the sprite, do not forget to change this
value, to be sure the client receive the new sprite (note: do the same for the
served `spritifier_generated.css` file).


4- How to add a new icon in the sprite ?
========================================
Add a new css rule into your `spritifier_definition.css` file.
Update your configuration file if necessary.
Run Spritifier.


5- How to handle complex css selectors ?
========================================
You already know how to display static images (icons). But maybe you are not
sure if Spritifier handles complex css selectors. Here are some possible (not
exhaustive) css selectors.

```
/* create a red icon with a hover state, and a static blue icon */
/* all icons have the same dimensions, factorize in one rule */
.red_icon,
.red_icon:hover,
.blue_icon {
	display:inline-block;
	width:20px;
	height:20px;
}

/* we cannot factorize background rules. */
/* NOTICE: you _must_ use the same selectors than the 3 rules before */
.red_icon 			{background:url(img/red_icon.png) no-repeat scroll 0 0;}
.red_icon:hover {background:url(img/green_icon.png) no-repeat scroll 0 0;}
.blue_icon 			{background:url(img/blue_icon.png) no-repeat scroll 0 0;}

/* And also rules with parent conditions */
#container .yellow_icon {
	display:inline-block;
	width:30px;
	height:20px;
	background:url(img/yellow_icon.png) no-repeat scroll 0 0;
}
```

6- How to enhance the sprite ?
==============================
When choosing the width and compaction method of the sprite, try to find the
value that minimize lost pixels.

Some optimizations can be performed on the outputed files. Spritifier does not
perform these optimization because external projects are already very good for
this task.

When the spritifier.png is generated, it can be optimized with external tools
such as pngcrush or optipng. There is a lot of projects available for this
purpose. Choose the one you prefer. Some can easily be inserted in your
continuous integration process.

The `spritifer_generated.css` file can also be optimised by removing useless
characters. Try the YUI compressor for instance. Other CSS optimisation projects
should exist. Choose the one you prefer and use it for all your css files.

