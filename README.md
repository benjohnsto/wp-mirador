# wp-mirador
A Wordpress plugin for embedding the [IIIF Mirador Viewer](https://projectmirador.org/). 

## Shortcode
The plugin makes available the [mirador] shortcode. This shortcode requires a manifest attribute, such as:

[mirador manifest="https://data.artmuseum.princeton.edu/iiif/objects/8146"]

__Optional__ parameters include:

- height:
  - a number indicating a pixel width, e.g. height="700"
  - a percentage
- width: (an integer indicating pixel height)
- align: right or left
- view: can be set to 'gallery'. If omitted, defaults to 'single'
- minimal: removes the toolbars
- canvas: indicate the desired canvas to display as default.
