<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 05.04.15
 * Time: 18:55
 */

namespace Cundd\PersistentObjectStore\Server\Session;

use Cundd\PersistentObjectStore\Server\Controller\MutableControllerResultInterface;
use Cundd\PersistentObjectStore\Server\Cookie\Constants as CookieConstants;
use Cundd\PersistentObjectStore\Server\Cookie\Cookie;
use Cundd\PersistentObjectStore\Server\Session\Constants as SessionConstants;
use Cundd\PersistentObjectStore\Server\ValueObject\MutableControllerResult;
use Cundd\PersistentObjectStore\Server\ValueObject\Request;

/**
 * A trait to provide a controller with the ability to load a session
 *
 * @package Cundd\PersistentObjectStore\Server\Session
 */
trait SessionControllerTrait
{
    /**
     * Session provider instance
     *
     * @var \Cundd\PersistentObjectStore\Server\Session\SessionProviderInterface
     * @Inject
     */
    protected $sessionProvider;

    /**
     * Returns the session loaded for the current request, or newly created one
     *
     * @return SessionInterface|null
     */
    public function getSession()
    {
        $session = $this->sessionProvider->loadForRequest($this->getRequest());
        if (!$session) {
            $session = $this->sessionProvider->create();
        }

        return $session;
    }

    /**
     * Returns a mutable Controller Result instance
     *
     * This method may be used to inject session cookies into the response. The arguments are directly passed to the
     * result's constructor.
     *
     * @param integer $statusCode
     * @param mixed   $data
     * @param string  $contentType
     * @param array   $headers
     * @return MutableControllerResultInterface
     */
    public function buildResponse($statusCode = null, $data = null, $contentType = null, $headers = array())
    {
        $response      = new MutableControllerResult($statusCode, $data, $contentType, $headers);
        $sessionCookie = new Cookie(SessionConstants::SESSION_ID_COOKIE_NAME, $this->getSession()->getIdentifier());

        $response->setHeaders(array_merge(
            $response->getHeaders(),
            [CookieConstants::SET_COOKIE_HEADER_NAME => $sessionCookie->toHeader()]
        ));
        //$response->addHeader(
        //    CookieConstants::SET_COOKIE_HEADER_NAME,
        //    $sessionCookie->toHeader()
        //);

        return $response;
    }

    /**
     * Returns the current Request Info instance
     *
     * @return Request
     */
    abstract public function getRequest();
}
