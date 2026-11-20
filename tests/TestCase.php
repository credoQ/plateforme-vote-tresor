<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les tests qui utilisent RefreshDatabase partent des données de référence (DatabaseSeeder) :
     * référentiel des structures de 2022 et rôles. Le chargement a lieu une fois, à la migration de la base de test.
     */
    protected bool $seed = true;
}
