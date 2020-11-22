<?php

namespace App\Http\Controllers;

class CmsController extends Controller
{
    public function pageAction($page)
    {
        // TODO: Move data to DB entities
        switch ($page) {
            case 'all':
                return response()->json([
                    'about' => '# About

## Vi0
"Vi0lation" is a German multigaming guild that formed on AirRivals DE (Prokyon, BCU) and is since playing many different online games together.

## TeamSpeak
Address: [vi0lation.de](ts3server://vi0lation.de)

## Website
The main purpose of this website was the advanced AirRivals DE ranking, providing near complete recordings of everydays ladders aswell as tools to analyze and compare them. 
Tools for other servers and general AO helpers have also been added.

First launched in 2012, it has seen quite some changes and relaunches.  
The current source code is available on GitHub, seperated into [Frontend](https://github.com/kronthto/vi0lation.de "vi0lation.de Frontend Source-Code") and [API-Backend](https://github.com/kronthto/api.vi0lation.de "Ranking-Data api.vi0lation.de Source-Code").
'
                ])->setPublic()->setMaxAge(7200);
            case 'arranking':
                return response()->json([
                    'arranking' => '### Motivation

AirRivals, the EU Version of Masangsofts Aceonline (former  <abbr title="Space Cowboy Online">SCO</abbr>), published the highscores once a day and only of the current day. It was not possible to view previous days.

Therefore, in 2012 I wrote a program that saves the data once a day, and this website to make it publicly accessible, aswell as performing deeper analysis on the data (progress/day, Nation-balance).

### Dataset

The first day data was recorded is the 2012-07-27, since then up to the 2016-08-24 - the day AR.de closed its gates - 1163 days are recorded. Unfortunately, mostly in the beginning, some days are missing, because the import wasn\'t stable. Since the 2014-01-05 every day is on record.

### Methodology

AR.de published 5 tables every day: level, fame, brigade_total, brigade_monthly, pvp (duels). These were copied without modification/normalization into the same schema. All processing for views is done using complex joins on these tables.

### Data

If you want to dig into the records yourself you are encouraged to! All historical data is directly available:

[Download the MySQL Dump](http://cdn.vi0lation.de/files/prokyon_2012_07_27_to_2016_08_24.7z "Prokyon Ranking Dump")

Use the API as this website does (CORS is enabled): [api.vi0lation.de](https://github.com/kronthto/api.vi0lation.de#endpoints "Ranking-Data api.vi0lation.de GitHub")'
                ])->setPublic()->setMaxAge(7200);
            default:
                return response('Page not found', 404);
        }
    }
}
