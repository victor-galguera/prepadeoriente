CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration

INTRODUCTION
------------

Current Maintainer: Philippe Joulot <philippe.joulot@laposte.net>

The entity_dialog_formatter module provides a new entity reference formatter
that allows you to display entities into a dialog.


REQUIREMENTS
------------

This module requires the following modules to be enabled:
field, file, image, language, link, path, taxonomy, text

INSTALLATION
------------

This is like any other module.

1. Add it to your project with composer
"composer require drupal/partner_link".

2. Enable the module

CONFIGURATION
-------------

1 Create your website partners into the taxonomy 'Partners'
(partner_link__partners)

2 You can now visit the page /partners which lists all your partners

3 You also can place the block 'Partner Link' into one of your region.
The block will list your partners that have been marked as listed into
the 'Partner Link' block.
