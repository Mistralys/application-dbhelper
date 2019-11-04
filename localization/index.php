<?php
/**
 * Translation UI for the localizable strings in the package.
 *
 * @package Application DBHelper
 * @subpackage Examples
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
    
    $root = __DIR__;
    
    $autoload = realpath($root.'/../vendor/autoload.php');
    
    // we need the autoloader to be present
    if(!file_exists($autoload)) {
        die('<b>ERROR:</b> Autoloader not present. Run composer update first.');
    }
    
    /**
     * The composer autoloader
     */
    require_once $autoload;
    
    // add the locales we wish to manage (en_UK is always present)
    \AppLocalize\Localization::addAppLocale('de_DE');
    \AppLocalize\Localization::addAppLocale('fr_FR');
    
    $source = \AppLocalize\Localization::addSourceFolder(
        'app-dbhelper', // Must be unique: used in file names and to access the source programmatically
        'Database heelper', // Human readable label
        'Application utils', // Group labels are used to group sources in the UI
        $root, // The localization files will be stored here
        realpath($root.'/../src') // The PHP and JavaScript source files to search through are here
    );
    
    // has to be called last after all sources and locales have been configured
    \AppLocalize\Localization::configure($root.'/storage.json', '');
    
    // create the editor UI and start it
    $editor = \AppLocalize\Localization::createEditor();
    $editor->display();
