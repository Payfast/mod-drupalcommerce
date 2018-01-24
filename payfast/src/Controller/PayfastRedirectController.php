<?php
/**Copyright (c) 2008 PayFast (Pty) Ltd
You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
*/

namespace Drupal\commerce_payfast\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This is a dummy controller for mocking an off-site gateway.
 */
class PayfastRedirectController implements ContainerInjectionInterface {

    /**
     * The current request.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $currentRequest;

    /**
     * Constructs a new DummyRedirectController object.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(RequestStack $request_stack) {
        $this->currentRequest = $request_stack->getCurrentRequest();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('request_stack')
        );
    }

    /**
     * Callback method which accepts POST.
     *
     * @throws \Drupal\commerce\Response\NeedsRedirectException
     */
    public function post() {
        $cancel = $this->currentRequest->request->get('cancel');
        $return = $this->currentRequest->request->get('return');
        $total = $this->currentRequest->request->get('total');

        if ($total > 20) {
            return new TrustedRedirectResponse($return);
        }

        return new TrustedRedirectResponse($cancel);
    }

    /**
     * Callback method which reacts to GET from a 302 redirect.
     *
     * @throws \Drupal\commerce\Response\NeedsRedirectException
     */
    public function on302() {
        $cancel = $this->currentRequest->query->get('cancel');
        $return = $this->currentRequest->query->get('return');
        $total = $this->currentRequest->query->get('total');

        if ($total > 20) {
            return new TrustedRedirectResponse($return);
        }

        return new TrustedRedirectResponse($cancel);
    }

}
