<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\EventListener;

use Negotiation\Negotiator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Chooses the format to user according to the Accept header and supported formats.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class AddFormatListener
{
    private $negotiator;
    private $formats;

    /**
     * @example $mimeTypes
     * <code>
     * $mimeTypes = [
     *     'application/json' => 'json',
     *     'application/x-json' => 'json',
     * ]
     * </code>
     */
    private $mimeTypes;

    /**
     * @param Negotiator $negotiator
     * @param array $formats
     * @example $formats
     * <code>
     * ['json' => ['application/json']]
     * </code>
     */
    public function __construct(Negotiator $negotiator, array $formats)
    {
        $this->negotiator = $negotiator;
        $this->formats = $formats;
    }

    /**
     * Sets the applicable format to the HttpFoundation Request.
     *
     * @param GetResponseEvent $event
     *
     * @throws NotFoundHttpException
     * @throws NotAcceptableHttpException
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $isNotApiRequest = function() use ($request) {
            return  !$request->attributes->has('_api_resource_class') && !$request->attributes->has('_api_respond');
        };

        if ($isNotApiRequest()) {
            return;
        }

        /** Populate mimeTypes */
        ($populateMineTypes = function() {
            if (null !== $this->mimeTypes) {
                return;
            }

            $this->mimeTypes = [];
            foreach ($this->formats as $format => $mimeTypes) {
                foreach ($mimeTypes as $mimeType) {
                    $this->mimeTypes[$mimeType] = $format;
                }
            }
        })();

        /** Adds API formats to the HttpFoundation Request. */
        ($addRequestFormats = function() use ($request) {

            foreach ($this->formats as $format => $mimeTypes) {
                $request->setFormat($format, $mimeTypes);
            }
        })();

        // Empty strings must be converted to null because the Symfony router doesn't support parameter typing before 3.2 (_format)
        $routeFormat = $request->attributes->get('_format') ?: null;

        if(is_string($routeFormat) && !isset($this->formats[$routeFormat])) {
            throw new NotFoundHttpException(sprintf('Format "%s" is not supported', $routeFormat));
        }

        if (is_null($routeFormat)) {
            $mimeTypes = array_keys($this->mimeTypes);
        } else {
            $mimeTypes = Request::getMimeTypes($routeFormat);
        }

        // First, try to guess the format from the Accept header
        $accept = $request->headers->get('Accept');
        if (null !== $accept) {
            if (null === $acceptHeader = $this->negotiator->getBest($accept, $mimeTypes)) {
                throw $this->getNotAcceptableHttpException($accept, $mimeTypes);
            }

            $request->setRequestFormat($request->getFormat($acceptHeader->getType()));

            return;
        }

        // Then use the Symfony request format if available and applicable
        $requestFormat = $request->getRequestFormat('') ?: null;
        if (null !== $requestFormat) {
            $mimeType = $request->getMimeType($requestFormat);

            if (isset($this->mimeTypes[$mimeType])) {
                return;
            }

            throw $this->getNotAcceptableHttpException($mimeType);
        }

        // Finally, if no Accept header nor Symfony request format is set, return the default format
        foreach ($this->formats as $format => $mimeType) {
            $request->setRequestFormat($format);

            return;
        }
    }


    /**
     * Retrieves an instance of NotAcceptableHttpException.
     *
     * @param string        $accept
     * @param string[]|null $mimeTypes
     *
     * @return NotAcceptableHttpException
     */
    private function getNotAcceptableHttpException(string $accept, array $mimeTypes = null): NotAcceptableHttpException
    {
        if (null === $mimeTypes) {
            $mimeTypes = array_keys($this->mimeTypes);
        }

        return new NotAcceptableHttpException(sprintf(
            'Requested format "%s" is not supported. Supported MIME types are "%s".',
            $accept,
            implode('", "', $mimeTypes)
        ));
    }
}
