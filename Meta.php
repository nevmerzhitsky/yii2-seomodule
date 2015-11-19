<?php
namespace nevmerzhitsky\seomodule;
use Yii;
use yii\web\View;
use yii\base\Component;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use nevmerzhitsky\seomodule\models\SeoMeta;
use yii\base\Model;

/**
 * Meta tags
 *
 * @package nevmerzhitsky\seomodule
 * @author Max Voronov <v0id@list.ru>
 *
 * @property string
 */
class Meta extends Component {

    const KEY_TITLE = 'title';

    const KEY_DESCRIPTION = 'description';

    const KEY_KEYWORDS = 'keywords';

    public $title = '';

    protected $_fixedTitle = false;

    /**
     *
     * @var yii\web\View
     */
    protected $_view;

    protected $_routeMetaData = [];

    protected $_paramsMetaData = [];

    protected $_defaultMetaData = [];

    protected $_userMetaData = [];

    protected $_variables = [];

    /**
     *
     * @var unknown
     */
    protected $_models = [];

    /**
     * Init component
     */
    public function init () {
        Yii::$app->view->on(View::EVENT_BEGIN_PAGE,
            [
                $this,
                '_applyMeta'
            ]);
    }

    /**
     * Set variables for autoreplace
     *
     * @param string|array $name
     * @param string $value
     * @return $this
     */
    public function setVar ($name, $value = '') {
        if (!empty($name)) {
            if (is_array($name)) {
                foreach ($name as $varName => $value) {
                    $this->_variables['%' . $varName . '%'] = $value;
                }
            } else {
                $this->_variables['%' . $name . '%'] = $value;
            }
        }
        return $this;
    }

    public function canNotChangeTitle ($value = true) {
        $this->_fixedTitle = !empty($value);
    }

    /**
     * Add model to list of current page models.
     *
     * @param Model $model
     */
    public function registerModel (Model $model) {
        $this->_models[] = $model;
    }

    /**
     * Apply metatags to page
     *
     * @param $event
     */
    protected function _applyMeta ($event) {
        $this->_view = $event->sender;

        $data = $this->_getMetaDataFromModels();
        $data = $this->_combineSeveralDataToOne($data);

        // $this->_getMetaData(Yii::$app->requestedRoute,
        // Yii::$app->requestedParams);
        // $data = ArrayHelper::merge($this->_defaultMetaData,
        // $this->_routeMetaData, $this->_paramsMetaData, $this->_userMetaData);
        $this->_prepareVars()
            ->_setTitle($data)
            ->_setMeta($data)
            ->_setRobots($data);
        // ->_setTags($data);
    }

    protected function _getMetaDataFromModels () {
        $result = [];

        /** @var $model Model */
        /** @var $beh \nevmerzhitsky\seomodule\behaviors\SeoModelBehavior */
        foreach ($this->_models as $model) {
            $beh = $model->getBehavior('seo');

            if (is_null($beh)) {
                continue;
            }

            // @TODO More complex work with site language?
            $lang = Yii::$app->language;
            $result[] = $beh->getSeoData($lang);
        }

        return $result;
    }

    protected function _combineSeveralDataToOne (array $dataOfSeveral) {
        $result = [];

        foreach ($dataOfSeveral as $data) {
            $result = array_merge_recursive($result, $data);
        }

        // Convert arrays to string.
        $result = array_map(
            function  ($v) {
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }

                return $v;
            }, $result);

        return $result;
    }

    /**
     * Init default variables for autoreplace
     *
     * @return $this
     */
    protected function _prepareVars () {
        $this->setVar(
            [
                'HOME_URL' => Url::home(true),
                'CANONICAL_URL' => Url::canonical(),
                'LOCALE' => Yii::$app->formatter->locale
            ]);

        return $this;
    }

    /**
     * Replace vars placeholders to values in string.
     *
     * @param string $str
     * @return string
     */
    protected function _substituteVars ($str) {
        return str_replace(array_keys($this->_variables), $this->_variables,
            trim($str));
    }

    /**
     * Set value for <title> tag.
     *
     * @param array $data
     * @return $this
     */
    protected function _setTitle ($data) {
        $data[static::KEY_TITLE] = $this->_substituteVars(
            $data[static::KEY_TITLE]);

        if (empty($data[static::KEY_TITLE]) ||
             ($this->_fixedTitle && !empty($this->_view->title))) {
            $data[static::KEY_TITLE] = $this->_view->title;
        }

        $this->setVar('SEO_TITLE', $data[static::KEY_TITLE]);

        $this->title = $data[static::KEY_TITLE];

        return $this;
    }

    /**
     * Set meta keywords and meta description tags
     *
     * @param array $data
     * @return $this
     */
    protected function _setMeta ($data) {
        static $names = [
            'keywords' => self::KEY_KEYWORDS,
            'description' => self::KEY_DESCRIPTION
        ];

        foreach (array_unique(array_keys($names)) as $key) {
            $data[$key] = $this->_substituteVars($data[$key]);
        }

        $data[static::KEY_KEYWORDS] = preg_replace('%,[ ]+%', ',',
            $data[static::KEY_KEYWORDS]);

        foreach ($names as $name => $key) {
            $var = 'SEO_META_' . strtoupper($name);

            if (!empty($data[$key])) {
                $this->_view->registerMetaTag(
                    [
                        'name' => $name,
                        'content' => $data[$key]
                    ]);
            }

            $this->setVar($var, $data[$key]);
        }

        return $this;
    }

    /**
     * Set meta robots tag
     *
     * @param array $data
     * @return $this
     */
    protected function _setRobots ($data) {
        if (!empty($data['robots'])) {
            $this->_view->registerMetaTag(
                [
                    'name' => 'robots',
                    'content' => $data['robots']
                ], 'seo-robots');
        }

        return $this;
    }

    /**
     * Set other meta tags
     * For example, OpenGraph tags
     *
     * @param array $data
     * @return $this
     */
    protected function _setTags ($data) {
        $tags = ArrayHelper::merge($this->_defaultMetaData['tags'],
            $data['tags']);

        if (!empty($tags)) {
            foreach ($tags as $tagName => $tagProp) {
                if (!empty($tagProp) && is_string($tagProp)) {
                    $tagProp = $this->_substituteVars($tagProp);
                }

                $this->_view->registerMetaTag(
                    [
                        'property' => $tagName,
                        'content' => $tagProp
                    ]);
            }
        }
        return $this;
    }

    /**
     * Get data from database
     *
     * @param string $route
     * @param array $params
     */
    protected function _getMetaData ($route, $params = null) {
        $params = json_encode($params);
        $model = SeoMeta::find()->where([
            'route' => '-'
        ])
            ->orWhere(
            [
                'and',
                'route=:route',
                [
                    'or',
                    'params IS NULL',
                    'params=:params'
                ]
            ],
            [
                ':route' => $route,
                ':params' => $params
            ])
            ->asArray()
            ->all();

        foreach ($model as $item) {
            $item = array_filter($item, 'strlen');
            if (!empty($item['tags']))
                $item['tags'] = (array) json_decode($item['tags']);
            if ($item['route'] == '-')
                $this->_defaultMetaData = $item;
            elseif ($item['route'] != '-' && empty($item['params']))
                $this->_routeMetaData = $item;
            elseif ($item['route'] != '-' && !empty($item['params']))
                $this->_paramsMetaData = $item;
        }
    }

    public function &__get ($prop) {
        return $this->_userMetaData[$prop];
    }

    public function __set ($prop, $value) {
        if (empty($prop))
            return;
        $this->_userMetaData[$prop] = &$value;
    }
}
