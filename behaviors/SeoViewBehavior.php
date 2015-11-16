<?php
namespace nevmerzhitsky\seomodule\behaviors;

/**
 *
 * @link Inspired by https://github.com/demisang/yii2-seo/
 */
use Yii;
use yii\base\Behavior;
use yii\web\View;
use yii\helpers\Html;

/**
 * Add ability of view to setup page title and meta-tags for SEO.
 *
 * @package nevmerzhitsky\seomodule
 */
class SeoViewBehavior extends Behavior {

    private $_pageTitle = '';

    private $_metaDescription = '';

    private $_metaKeywords = '';

    private $_metaRobots = '';

    /**
     * Set meta-params for current page.
     *
     * @param array|SeoModelBehavior|string $title - array: ["title"=>"Page
     *        Title", "desc"=>"Page Descriptions", "keywords"=>"Page, Keywords"]
     *        - string: used as title of page
     * @param string $desc Meta description
     * @param string|string[] $keywords Meta keywords string or array of
     *        keywords.
     * @return static
     */
    public function setSeoData ($title, $desc = '', $keywords = '') {
        $data = $title;
        if ($title instanceof SeoModelBehavior) {
            $meta = $title->getSeoData();
            $data = [
                'title' => $meta[SeoModelBehavior::TITLE_KEY],
                'desc' => $meta[SeoModelBehavior::DESC_KEY],
                'keywords' => $meta[SeoModelBehavior::KEYS_KEY]
            ];
        } elseif (is_string($title)) {
            $data = [
                'title' => $title,
                'desc' => $desc,
                'keywords' => !is_array($keywords) ? $keywords : implode(', ',
                    $keywords)
            ];
        }

        if (isset($data['title'])) {
            $this->_pageTitle = $this->_normalizeStr($data['title']);
        }
        if (isset($data['desc'])) {
            $this->_metaDescription = $this->_normalizeStr($data['desc']);
        }
        if (isset($data['keywords'])) {
            $this->_metaKeywords = $this->_normalizeStr($data['keywords']);
        }

        return $this;
    }

    /**
     * Set robots meta-tag.
     *
     * @param string $content Content of the meta-tag.
     */
    public function setMetaRobots ($content = 'noindex, follow') {
        $this->_metaRobots = trim($content);
    }

    /**
     * Render HTML with configured title and meta tags.
     *
     * @return string
     */
    public function renderMetaTags () {
        /* @var $view View */
        $view = $this->owner;

        $map = [
            'description' => $this->_metaDescription,
            'keywords' => $this->_metaKeywords,
            'robots' => $this->_metaRobots
        ];

        foreach ($map as $name => $value) {
            if (empty($value)) {
                continue;
            }

            $view->registerMetaTag(
                [
                    'name' => $name,
                    'content' => Html::encode($this->_normalizeStr($value))
                ], "meta-{$name}");
        }

        // @TODO Add title template to params.
        if (!empty($this->_pageTitle)) {
            $title = $this->_pageTitle . ' - ' . Yii::$app->name;
        } else {
            $title = Yii::$app->name;
        }

        $title = Html::encode($this->_normalizeStr($title));

        return "<title>{$title}</title>" . PHP_EOL;
    }

    /**
     *
     * @param string $str
     * @return string
     */
    private function _normalizeStr ($str) {
        $str = strip_tags($str);
        // Replace many various sequential space chars to one.
        $str = trim(preg_replace('/[\s]+/isu', ' ', $str));

        return $str;
    }
}