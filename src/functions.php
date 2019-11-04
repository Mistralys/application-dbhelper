<?php

namespace AppDB;

function t()
{
    return call_user_func_array('\AppLocalize\t', func_get_args());
}

function pt()
{
    return call_user_func_array('\AppLocalize\pt', func_get_args());
}

function pts()
{
    return call_user_func_array('\AppLocalize\pts', func_get_args());
}

/**
 * Initializes the utilities: this is called automatically
 * because this file is included in the files list in the
 * composer.json, guaranteeing it is always loaded.
 */
function init()
{
    if(!class_exists('\AppLocalize\Localization')) {
        return;
    }
    
    $installFolder = realpath(__DIR__.'/../');
    
    // Register the classes as a localization source,
    // so they can be found, and use the bundled localization
    // files.
    \AppLocalize\Localization::addSourceFolder(
        'application-dbhelper',
        'Application Database Helper',
        'Composer Packages',
        $installFolder.'/localization',
        $installFolder.'/src'
    );
}

init();
