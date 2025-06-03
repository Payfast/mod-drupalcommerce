<?php

namespace Drupal\commerce_payfast\Controller;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This is a dummy controller for mocking an off-site gateway.
 */
class PayfastRedirectController implements ContainerInjectionInterface
{
    /**
     * The current request.
     *
     * @var Request|null
     */
    protected ?Request $currentRequest;

    /**
     * Constructs a new DummyRedirectController object.
     *
     * @param RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(RequestStack $request_stack)
    {
        $this->currentRequest = $request_stack->getCurrentRequest();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('request_stack')
        );
    }

    /**
     * Callback method which accepts POST.
     *
     */
    public function post(): TrustedRedirectResponse
    {
        $cancel = $this->currentRequest->request->get('cancel');
        $return = $this->currentRequest->request->get('return');
        $total  = $this->currentRequest->request->get('total');

        if ($total > 20) {
            return new TrustedRedirectResponse($return);
        }

        return new TrustedRedirectResponse($cancel);
    }

    /**
     * Callback method which reacts to GET from a 302 redirect.
     *
     */
    public function on302(): TrustedRedirectResponse
    {
        $cancel = $this->currentRequest->query->get('cancel');
        $return = $this->currentRequest->query->get('return');
        $total  = $this->currentRequest->query->get('total');

        if ($total > 20) {
            return new TrustedRedirectResponse($return);
        }

        return new TrustedRedirectResponse($cancel);
    }

}
