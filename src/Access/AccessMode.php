<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Access;

/**
 * How a channel treats visitors who are not signed in. The backed values are stored in the
 * `access_mode` column of the channel and are part of the public contract: never rename them.
 */
enum AccessMode: string
{
    /** Catalogue, prices and purchase for everybody (Sylius default). */
    case Open = 'open';

    /** Catalogue for everybody; prices and purchase for signed-in customers only. */
    case PricesHidden = 'prices_hidden';

    /** Everything but the exempt routes (home, login, registration...) for signed-in customers only. */
    case Closed = 'closed';
}
