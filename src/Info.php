<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Debug;

use function ob_get_clean;
use function ob_start;
use function phpinfo;
use function trim;

class Info
{

    public static function all(): void
    {
        ob_start();
        phpinfo();
        /** @var string $result */
        $result = ob_get_clean();

        Debugger::send(Message::DUMP, trim($result));
    }

}
