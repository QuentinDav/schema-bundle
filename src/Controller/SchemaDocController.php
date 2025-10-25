<?php
namespace Qd\SchemaBundle\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;

final class SchemaDocController
{
    public function __construct(private KernelInterface $kernel) {}

    public function index(): Response
    {
        $index = $this->kernel->locateResource('@QdSchemaBundle/Resources/public/schema/index.html');
        if (!is_file($index)) {
            throw new NotFoundHttpException('index.html introuvable');
        }
        return new Response(file_get_contents($index));
    }

    public function asset(string $path): BinaryFileResponse|Response
    {
        if (!str_contains($path, '.')) {
            $index = $this->kernel->locateResource('@QdSchemaBundle/Resources/public/schema/index.html');
            return new Response(file_get_contents($index));
        }

        $base = realpath($this->kernel->locateResource('@QdSchemaBundle/Resources/public/schema/'));
        $file = $base ? realpath($base . DIRECTORY_SEPARATOR . ltrim($path, '/')) : false;

        if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($file);

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $map = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'map'  => 'application/json',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'otf'  => 'font/otf',
        ];
        $response->headers->set('Content-Type', $map[$ext] ?? 'application/octet-stream');
        $response->setPublic();
        $response->headers->set('Cache-Control', 'max-age=31536000, immutable');

        return $response;
    }

}
