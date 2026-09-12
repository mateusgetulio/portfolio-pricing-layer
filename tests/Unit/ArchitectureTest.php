<?php

arch('the pricing domain does not depend on Laravel')
    ->expect('App\Pricing')
    ->not->toUse(['Illuminate', 'config', 'env', 'app', 'now', 'collect']);

arch('pricing data objects are final and readonly')
    ->expect('App\Pricing\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('pricing enums are string backed')
    ->expect('App\Pricing\Enums')
    ->toBeStringBackedEnums();

arch('pricing exceptions are final')
    ->expect('App\Pricing\Exceptions')
    ->toBeFinal();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();
