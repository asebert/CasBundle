<?php

namespace L3\Bundle\CasBundle\Controller;

use phpCAS as phpCAS;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LogoutController extends Controller
{
    public function logoutAction(Request $request)
    {
        if ($this->container->getParameter('cas')['LogoutReferer']) {
            $referer = $request->headers->get('referer');

            if (isset($referer) && is_null($request->query->get('noreferer'))) {
                \phpCas::logoutWithRedirectService($referer);
            }
        }

        if (array_key_exists('casLogoutTarget', $this->container->getParameter('cas'))) {
            $route = $this->generateUrl($this->container->getParameter('cas')['casLogoutTarget'], array(), UrlGeneratorInterface::ABSOLUTE_URL);
            \phpCas::logoutWithRedirectService($route);
        } else {
            \phpCAS::logout();
        }
    }
}
