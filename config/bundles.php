<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
];

if (class_exists(\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
    $bundles[\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true];
}

if (class_exists(\Symfony\Bundle\DebugBundle\DebugBundle::class)) {
    $bundles[\Symfony\Bundle\DebugBundle\DebugBundle::class] = ['dev' => true];
}

if (class_exists(\Symfony\Bundle\MakerBundle\MakerBundle::class)) {
    $bundles[\Symfony\Bundle\MakerBundle\MakerBundle::class] = ['dev' => true];
}

return $bundles;
