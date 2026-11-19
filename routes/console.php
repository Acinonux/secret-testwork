<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('secrets:purge')->everyMinute()->withoutOverlapping();
