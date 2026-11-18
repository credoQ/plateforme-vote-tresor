<?php

use Illuminate\Support\Facades\Schedule;

// R39 : ouverture et fermeture des scrutins à leur date-heure.
Schedule::command('scrutins:basculer')->everyMinute()->withoutOverlapping();
