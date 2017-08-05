<?php
declare(strict_types=1);

namespace Cundd\PersistentObjectStore\Server\Cookie;

use Cundd\PersistentObjectStore\Server\ValueObject\RequestInterface;

/**
 * Interface for classes that can parse and transform request cookies
 */
interface CookieParserInterface
{
    /**
     * Parse the cookie data from the given request and transform it into objects
     *
     * @param RequestInterface|\React\Http\Request $request
     * @return Cookie[] Returns a dictionary with the cookie names as keys
     */
    public function parse($request);
} 