<?php

namespace App\Http\Controllers;

use App\Album;
use App\Configs;
use App\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class AlbumsController extends Controller
{

    /**
     * @return array|string Returns an array of albums or false on failure.
     */
    public function get(Request $request) {

        // Initialize return var
        $return = array(
            'smartalbums' => null,
            'albums'      => null,
            'num'         => 0
        );

        // Get SmartAlbums
        if (Session::get('login')) $return['smartalbums'] = self::getSmartAlbums();

        // Albums query
        if (Session::get('login'))
        {
            $albums_sql = Album::orderBy(Configs::get_value('sortingAlbums_col'),Configs::get_value('sortingAlbums_order'));
        }
        else
        {
            $albums_sql = Album::where('public','=','1')->where('visible_hidden','=','1')
                ->orderBy(Configs::get_value('sortingAlbums_col'),Configs::get_value('sortingAlbums_order'));
        }

        $albums = $albums_sql->get();

        // For each album
        foreach ($albums as $album_model){

            // Turn data from the database into a front-end friendly format
            $album = $album_model->prepareData();

            // Thumbs
            if ((!Session::get('login') && $album_model->password === null)||
                (Session::get('login'))) {

                $thumbs = Photo::select('thumbUrl')
                    ->where('album_id','=',$album_model->id)
                    ->orderBy('star','DESC')
                    ->orderBy(Configs::get_value('sortingPhotos_col'),Configs::get_value('sortingPhotos_order'))
                    ->limit(3)->get();

                if ($thumbs === false) return 'false';

                // For each thumb
                $k = 0;
                $album['sysstamp'] = $album_model['created_at'];
                $album['thumbs'] = array();
                foreach ($thumbs as $thumb) {
                    $album['thumbs'][$k] = Config::get('defines.urls.LYCHEE_URL_UPLOADS_THUMB') . $thumb->thumbUrl;
                    $k++;
                }

            }

            // Add to return
            $return['albums'][] = $album;

        }

        // Num of albums
        $return['num'] = $albums_sql->count();

        return $return;

    }


    static private function gen_return($return, $photos_sql, $kind)
    {
        $photos = $photos_sql->get();
        $i        = 0;

        $return[$kind] = array(
            'thumbs' => array(),
            'num'    => $photos_sql->count()
        );

        foreach ($photos as $photo) {
            if ($i<3) {
                $return[$kind]['thumbs'][$i] = Config::get('defines.urls.LYCHEE_URL_UPLOADS_THUMB') . $photo->thumbUrl;
                $i++;
            } else break;
        }

        return $return;
    }

    /**
     * @return array|false Returns an array of smart albums or false on failure.
     */
    private function getSmartAlbums() {

        // Initialize return var
        $return = array(
            'unsorted' => null,
            'public'   => null,
            'starred'  => null,
            'recent'   => null
        );

        /**
         * Unsorted
         */
        $photos_sql = Photo::select_unsorted(Photo::select('thumbUrl'))->limit(3);
        $return = self::gen_return($return, $photos_sql, 'unsorted');

        /**
         * Starred
         */
        $photos_sql = Photo::select_stars(Photo::select('thumbUrl'))->limit(3);
        $return = self::gen_return($return, $photos_sql, 'starred');

        /**
         * Public
         */
        $photos_sql = Photo::select_public(Photo::select('thumbUrl'))->limit(3);
        $return = self::gen_return($return, $photos_sql, 'public');

        /**
         * Recent
         */
        $photos_sql = Photo::select_recent(Photo::select('thumbUrl'))->limit(3);
        $return = self::gen_return($return, $photos_sql, 'recent');

        // Return SmartAlbums
        return $return;

    }

}
