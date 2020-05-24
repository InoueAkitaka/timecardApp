<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit51c3888851ac836ed1df447b0904bde5
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LINE\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LINE\\' => 
        array (
            0 => __DIR__ . '/..' . '/linecorp/line-bot-sdk/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit51c3888851ac836ed1df447b0904bde5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit51c3888851ac836ed1df447b0904bde5::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
