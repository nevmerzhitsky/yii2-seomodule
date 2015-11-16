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
 * Управление установкой SEO-параметров для страницы
 *
 * @package nevmerzhitsky\seomodule
 */
class SeoViewBehavior extends Behavior {

    private $_pageTitle = '';

    private $_metaDescription = '';

    private $_metaKeywords = '';

    private $_noIndex = false;

    /**
     * Установка meta параметров страницы
     *
     * @param mixed $title 1) массив:
     *        ["title"=>"Page Title", "desc"=>"Page Descriptions",
     *        "keys"=>"Page, Keywords"]
     *        2) SeoModelBehavior
     *        3) Строка для title страницы
     * @param string $desc Meta description
     * @param mixed $keys Meta keywords, строка либо массив ключевиков
     *
     * @return static
     */
    public function setSeoData ($title, $desc = '', $keys = '') {
        $data = $title;
        if ($title instanceof SeoModelBehavior) {
            // Вытаскиваем данные из модельки, в которой есть SeoModelBehavior
            $meta = $title->getSeoData();
            $data = [
                'title' => $meta[SeoModelBehavior::TITLE_KEY],
                'desc' => $meta[SeoModelBehavior::DESC_KEY],
                'keys' => $meta[SeoModelBehavior::KEYS_KEY]
            ];
        } elseif (is_string($title)) {
            $data = [
                'title' => $title,
                'desc' => $desc,
                'keys' => !is_array($keys) ? $keys : implode(', ', $keys)
            ];
        }
        if (isset($data['title'])) {
            $this->_page_title = $this->normalizeStr($data['title']);
        }
        if (isset($data['desc'])) {
            $this->_metaDescription = $this->normalizeStr($data['desc']);
        }
        if (isset($data['keys'])) {
            $this->_metaKeywords = $this->normalizeStr($data['keys']);
        }

        return $this;
    }

    public function renderMetaTags () {
        /* @var $view View */
        $view = $this->owner;
        $title = !empty($this->_page_title) ? $this->_page_title . ' - ' .
             Yii::$app->name : Yii::$app->name;
        echo '<title>' . Html::encode($this->normalizeStr($title)) . '</title>' .
             PHP_EOL;
        if (!empty($this->_metaDescription)) {
            $view->registerMetaTag(
                [
                    'name' => 'description',
                    'content' => Html::encode(
                        $this->normalizeStr($this->_metaDescription))
                ]);
        }
        if (!empty($this->_metaKeywords)) {
            $view->registerMetaTag(
                [
                    'name' => 'keywords',
                    'content' => Html::encode(
                        $this->normalizeStr($this->_metaKeywords))
                ]);
        }
        if (!empty($this->_noIndex)) {
            $view->registerMetaTag(
                [
                    'name' => 'robots',
                    'content' => $this->_noIndex
                ]);
        }
    }

    /**
     * Нормализует строку, подготоваливает её для отображения
     *
     * @param string $str
     * @return string
     */
    private function normalizeStr ($str) {
        // Удаляем теги из текста
        $str = strip_tags($str);
        // Заменяем все пробелы, переносы строк и табы на один пробел
        $str = trim(preg_replace('/[\s]+/is', ' ', $str));

        return $str;
    }

    /**
     * Установить meta-тег noindex для текущей страницы
     *
     * @param boolean $follow Разрешить поисковикам следовать по ссылкам? Если
     *        FALSE,
     *        то в мета-тег будет добавлено nofollow
     */
    public function noIndex ($follow = true) {
        $content = 'noindex, ' . ($follow ? 'follow' : 'nofollow');

        $this->_noIndex = $content;
    }
}
