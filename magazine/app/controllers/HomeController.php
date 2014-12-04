<?php


function like($string, $escape_char = '\\') {
    return str_replace(
        array($escape_char, '_', '%'),
        array($escape_char.$escape_char, $escape_char.'_', $escape_char.'%'),
        $string
    );
}

function wild($string) {
    return DB::connection()->getPdo()->quote('%'.like($string).'%');
}


/**
 * Controller na zobrazenie domovskej stránky
 */
class HomeController extends BaseController {

    /**
     * Zobrazí domovskú stránku
     * @return type
     */
    public function showWelcome() {
        $maxPages = ceil(Article::published()->count() / 9);
        $articles = Article::published()->orderBy('updated_at', 'DESC')->simplePaginate(9);
        return View::make('index', array(
                    'articles' => $articles,
                    'maxPages' => $maxPages
        ));
    }

    public function tags() {
        $input = Input::all();

        if (!Request::ajax() || !$query = Input::get('query')) {
            return Response::json(array('result' => $input));
        }

        $quoted_query = wild($query);
        $tags = Tag_group::
                whereRaw("name ilike " . $quoted_query)
                ->take(10)
                ->lists('name');

        return Response::json(array('result' => $tags));
    }

    public function search() {
        if (!Input::has('hladanie')) {
            return Redirect::action('HomeController@showWelcome');
        }
        if (!$query = Input::get('hladanie')) {
            $query = '';
        }

        $query = strtolower(trim($query));
        if (!$query) {
            // search for nothig => show everything
            return Redirect::action('HomeController@showWelcome');
        }

        $date_validator = Validator::make(
            array('datum' => $query), array('datum' => 'date_format:"d.m.Y"')
        );

        $quoted_query = wild($query);
        $articles = Article::published();
        if ($date_validator->passes()) {
            $myDate = DateTime::createFromFormat('d.m.Y', $query);
            $iso8601date = $myDate->format('Y-m-d');
            $articles = $articles
                    ->where(DB::raw('DATE(updated_at)'), '>=', "'" . $iso8601date . "'")
                    ->orderBy('updated_at', 'DESC');
        } else {
            $articles = $articles
                    ->leftJoin('users', 'users.id', '=', 'articles.user_id')
                    ->leftJoin('tags', 'tags.id_article', '=', 'articles.id')
                    ->leftJoin('tag_groups', 'tags.id_tag', '=', 'tag_groups.id')
                    ->select('articles.*')
                    ->distinct()
                    ->where(function($q2) use ($quoted_query) {
                        $q2->whereRaw("articles.title ilike " . $quoted_query)
                        ->orWhere(function($q3) use ($quoted_query) {
                            $q3->whereRaw("tag_groups.name ilike " . $quoted_query);
                        })
                        ->orWhere(function($q4) use ($quoted_query) {
                            $q4->whereRaw("articles.text ilike " . $quoted_query);
                        })
                        ->orWhere(function($q5) use ($quoted_query) {
                            $q5->whereRaw("users.name ilike " . $quoted_query);
                        });
                    })
                    ->orderBy('updated_at', 'DESC');
        }
        $maxPages = ceil(count($articles->get()) / 9);
        return View::make('index', array(
                    'articles' => $articles->orderBy('title', 'asc')->simplePaginate(9),
                    'maxPages' => $maxPages,
                    'query' => $query,
        ));
    }

    /**
     * Zobrazí domovskú stránku s článkami, ktoré majú určité kľúčové slovo
     * @param type $id
     * @return type
     */
    public function findTag($id = null) {
        $idArticles = [];
        foreach (Tag::where('id_tag', '=', $id)->get() as $tag) {
            $idArticles[] = $tag->id_article;
        }
        if (!count($idArticles)) {
            return Redirect::action('HomeController@showWelcome');
        }
        $maxPages = ceil(Article::whereIn('id', $idArticles)->published()->count() / 9);
        $articles = Article::whereIn('id', $idArticles)->orderBy('updated_at', 'DESC')->published()->simplePaginate(9);
        return View::make('index', array(
                    'articles' => $articles,
                    'maxPages' => $maxPages));
    }

}
