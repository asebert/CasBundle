<?php

namespace L3\Bundle\CasBundle\Security;

use phpCAS as phpCAS;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class CasListener implements ListenerInterface
{
    protected $tokenStorage;
    protected $authenticationManager;
    protected $config;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, $config)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->config = $config;
    }

    public function handle(GetResponseEvent $event)
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $agent = $event->getRequest()->headers->get('User-Agent');
        $bot = $this->bot($agent);

        \phpCAS::setDebug(false);
        \phpCas::client(CAS_VERSION_2_0, $this->getParameter('host'), $this->getParameter('port'), is_null($this->getParameter('path')) ? '' : $this->getParameter('path'), true);
        if (is_bool($this->getParameter('ca')) && $this->getParameter('ca') == false) {
            \phpCAS::setNoCasServerValidation();
        } else {
            \phpCAS::setCasServerCACert($this->getParameter('ca'));
        }
        if ($this->getParameter('handleLogoutRequest')) {
            if ($event->getRequest()->request->has('logoutRequest')) {
                $this->checkHandleLogout($event);

            }
            $logoutRequest = $event->getRequest()->request->get('logoutRequest');

            \phpCAS::handleLogoutRequests(true);
        } else {
            \phpCAS::handleLogoutRequests(false);
        }
        if ($this->getParameter('force') && $bot == false) {
            \phpCAS::forceAuthentication();
            $force = true;
        } else {
            $force = false;
            if (!isset($_SESSION['cas_user']) && $bot == false) {
                $auth = \phpCAS::checkAuthentication();
                if ($auth) {
                    $_SESSION['cas_user'] = \phpCAS::getUser();
                } else {
                    $_SESSION['cas_user'] = false;
                }

            } 
        }

        if (!$force) {
            if(!isset($_SESSION['cas_user'])){
                $_SESSION['cas_user']=null;
            }
            if (!$_SESSION['cas_user']) {
                $token = new CasToken(array('ROLE_ANON'));
                $token->setUser('__NO_USER__');
            } else {
                $token = new CasToken();
                $token->setUser($_SESSION['cas_user']);
            }
            $this->tokenStorage->setToken($this->authenticationManager->authenticate($token));
            return;
        }

        $token = new CasToken();
        $token->setUser(\phpCAS::getUser());

        try {
            $authToken = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($authToken);
        } catch (AuthenticationException $failed) {
            $response = new Response();
            $response->setStatusCode(403);
            $event->setResponse($response);
        }
    }

    public function getParameter($key)
    {
        if (!array_key_exists($key, $this->config)) {
            throw new InvalidConfigurationException('l3_cas.' . $key . ' is not defined');
        }
        return $this->config[$key];
    }

    /**
     * Cette fonction sert à vérifier le global logout, PHPCAS n'arrive en effet pas à le gérer étrangement dans Symfony2
     * @param GetResponseEvent $event
     */
    public function checkHandleLogout(GetResponseEvent $event)
    {
        // Récupération du paramètre
        $logoutRequest = $event->getRequest()->request->get('logoutRequest');
        // Les chaines recherchés
        $open = '<samlp:SessionIndex>';
        $close = '</samlp:SessionIndex>';

        // Isolation de la clé de session
        $begin = strpos($logoutRequest, $open);
        $end = strpos($logoutRequest, $close, $begin);
        $sessionID = substr($logoutRequest, $begin + strlen($open), $end - strlen($close) - $begin + 1);
        $sessionID = str_replace('.', '', $sessionID);
        // Changement de session et destruction pour forcer l'authentification CAS à la prochaine visite
        session_id($sessionID);
        session_destroy();

    }
    

/**
 * Check if the given user agent string is one of a crawler, spider, or bot.
 *
 * @param string $user_agent
 *   A user agent string (e.g. Googlebot/2.1 (+http://www.google.com/bot.html))
 *
 * @return bool
 *   TRUE if the user agent is a bot, FALSE if not.
 */
public function bot($user_agent) {
  // User lowercase string for comparison.
  $user_agent = strtolower($user_agent);
  // A list of some common words used only for bots and crawlers.
  $bot_identifiers = array(
    'bot',
    'slurp',
    'yandex',
    'embedly',
    'link',
    'outbrain',
    'w3c',
    'vkshare',  
    'crawler',
    'spider',
    'baiduspider',
    'curl',
    'rogerbot',  
    'facebook',
    'fetch',
  );
  // See if one of the identifiers is in the UA string.
  foreach ($bot_identifiers as $identifier) {
    if (strpos($user_agent, $identifier) !== FALSE) {
      return TRUE;
    }
  }
  return FALSE;
}


}
