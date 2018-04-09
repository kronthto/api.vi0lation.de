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
Vi0lation is a German multigaming clan/guild/brigade that formed on AirRivals DE (Prokyon, BCU), and since then continued playing various other MMOs & MOBAs together.

## TeamSpeak
Address: [vi0lation.de](ts3server://vi0lation.de)

## Website
The main purpose of this website is the [advanced AirRivals DE ranking](/ranking), providing near complete recordings of everydays ladders aswell as tools to analyze and compare them.

First launched in 2012, it has seen quite some changes and relaunches. The first version being a bunch of PHP Scripts somehow fitting together it has evolved into an React SPA/PWA. 
The current source code is available on GitHub, seperated into [Frontend](https://github.com/kronthto/vi0lation.de) and [API-Backend](https://github.com/kronthto/api.vi0lation.de).

## Videos
[<img src="//cdn.vi0lation.de/img/yt.svg" width="48" height="48" alt="YouTube" />](https://www.youtube.com/watch?v=Act1YKmPt2w) [<img src="//cdn.vi0lation.de/img/yt.svg" width="48" height="48" alt="YouTube" />](https://www.youtube.com/watch?v=OBiN8E8j7AM)'
                ]);
            default:
                return response('Page not found', 404);
        }
    }
}
