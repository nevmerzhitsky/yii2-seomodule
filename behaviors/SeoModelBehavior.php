<?php
namespace nevmerzhitsky\seomodule\behaviors;

/**
 *
 * @link Inspired by https://github.com/demisang/yii2-seo/
 */
use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Url;
use yii\validators\UniqueValidator;
use yii\validators\Validator;

/**
 * Behavior to work with SEO meta options
 *
 * @package nevmerzhitsky\seomodule
 * @property ActiveRecord $owner
 */
class SeoModelBehavior extends Behavior {
    const TITLE_KEY = 'title';
    const DESC_KEY = 'desc';
    const KEYS_KEY = 'keys';

    public static function keyToLabel ($key) {
        static $map = [
            self::TITLE_KEY => 'Title',
            self::DESC_KEY => 'Description',
            self::KEYS_KEY => 'Keywords'
        ];

        return $map[$key];
    }

    /** @var string The name of the field responsible for SEO:url */
    public $urlField;

    /**
     *
     * @var string|callable The name of the field which will form the SEO:url,
     *      or function,
     */
    private $_urlProduceField = 'title';

    /**
     *
     * @var string|callable PHP-expression that generates field SEO:title
     */
    private $_titleProduceFunc;

    /** @var string|callable PHP-expression that generates field SEO:desciption */
    private $_descriptionProduceFunc;

    /** @var string|callable PHP-expression that generates field SEO:keywords */
    private $_keysProduceFunc;

    /**
     *
     * @var string The name of the resulting field, which will be stored in a
     *      serialized form of all SEO-parameters
     */
    public $metaField;

    /** @var boolean|callable Whether to allow the user to change the SEO-data */
    public $clientChange = true;

    /** @var integer The maximum length of the field SEO:url */
    private $_maxUrlLength = 70;

    /** @var integer The maximum length of the field Title */
    private $_maxTitleLength = 70;

    /** @var integer The maximum length of the field Description */
    private $_maxDescLength = 130;

    /** @var integer The maximum length of the field Keywords */
    private $_maxKeysLength = 150;

    /** @var array Forbidden for use SEO:url names */
    private $_stopNames = [
        'create',
        'update',
        'delete',
        'view',
        'index'
    ];

    /** @var array List of languages that should be available to SEO-options */
    public $languages = [];

    /**
     *
     * @var string The name of a controller class, which operates the current
     *      model.
     *      Must be specified for a list of actions of the controller to seo_url
     */
    private $_controllerClassName = '';

    /** @var boolean Is it necessary to use only lowercase when generating seo_url */
    private $_toLowerSeoUrl = true;

    /** @var Query Additional criteria when checking the uniqueness of seo_url */
    private $_uniqueUrlFilter;

    /** @var string encoding site */
    private $_encoding = 'UTF-8';

    /** @var array Array configuration that overrides the above settings */
    public $seoConfig = [];

    /** @var array Saved actions of controllers for SEO:url stop list */
    private static $_controllersActions = [];

    public function events () {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind'
        ];
    }

    public function attach ($owner) {
        parent::attach($owner);

        // Apply the configuration options
        foreach ($this->seoConfig as $key => $value) {
            if ($this->hasProperty($key)) {
                $var = $key;
            } else {
                $var = '_' . $key;
            }

            $this->$var = $value;
        }

        $this->languages = (array) $this->languages;
        // If there was not passed any language - we use only one system
        // language
        if (!count($this->languages)) {
            $this->languages = [
                Yii::$app->language
            ];
        }
        // if the current user can see and edit SEO-data model
        if (is_callable($this->clientChange)) {
            $this->clientChange = call_user_func($this->clientChange, $owner);
        }

        // Determine the controller and add it actions to the seo url stop list
        if (!empty($this->urlField) && !empty($this->_controllerClassName)) {
            if (isset(static::$_controllersActions[$this->_controllerClassName])) {
                // Obtain the previously defined controller actions
                $buffer = static::$_controllersActions[$this->_controllerClassName];
            } else {
                // Get all actions of controller
                $reflection = new \ReflectionClass($this->_controllerClassName);
                $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
                $controller = $reflection->newInstance(Yii::$app->getUniqueId(), null);
                // Add all reusable controller actions
                $buffer = array_keys($controller->actions());
                // Loop through all the main controller actions
                foreach ($methods as $method) {
                    /* @var $method \ReflectionMethod */
                    $name = $method->getName();
                    if ($name !== 'actions' && substr($name, 0, 6) == 'action') {
                        $action = substr($name, 6, strlen($name));
                        $action[0] = strtolower($action[0]);
                        $buffer[] = $action;
                    }
                }

                // Save controller actions for later use
                static::$_controllersActions[$this->_controllerClassName] = $buffer;
            }

            // Merge controller actions with actions from config behavior
            $this->_stopNames = array_unique(array_merge($this->_stopNames, $buffer));
        }
    }

    public function beforeValidate () {
        $model = $this->owner;

        if (!empty($this->metaField)) {
            // Loop through all the languages available, it is often only one
            foreach ($this->languages as $lang) {
                // Loop through the fields and cut the long strings to set
                // allowed length
                foreach ($this->getMetaFields() as $meta_param_key => $meta_param_value_generator) {
                    $this->_applyMaxLength($meta_param_key, $lang);
                }
            }
        }

        if (empty($this->urlField)) {
            // If do not need to work with SEO:url, then skip further work
            return;
        }

        // Add UNIQUE validator for SEO:url field
        $validator = Validator::createValidator(UniqueValidator::className(), $model,
            $this->urlField, [
                'filter' => $this->_uniqueUrlFilter
            ]);

        // If SEO: url is not filled by the user, then generate its value
        $urlFieldVal = trim((string) $model->{$this->urlField});
        if ($urlFieldVal === '') {
            $urlFieldVal = $this->_getProduceFieldValue($this->_urlProduceField);
        }
        // Transliterated string and remove from it the extra characters
        $seoUrl = $this->_getSeoName($urlFieldVal, $this->_maxUrlLength, $this->_toLowerSeoUrl);

        // If there is a match with banned names, then add to the url underbar
        // to the end
        while (in_array($seoUrl, $this->_stopNames)) {
            $seoUrl .= '_';
        }

        $model->{$this->urlField} = $seoUrl;
        // Start the first unique validation
        $validator->validateAttribute($model, $this->urlField);

        // Run the validation of up to 50 times, until there is a unique SEO:url
        // name
        $i = 0;
        while ($model->hasErrors($this->urlField)) {
            // Remove the error message received in the last validation
            $model->clearErrors($this->urlField);

            // If failed 50 times, then something went wrong...
            if (++$i > 50) {
                // We establish SEO: url to a random hash
                $model->{$this->urlField} = md5(uniqid());
                // Finish "infinite" loop
                break;
            }

            // Add "_" at the end of SEO:url
            $newSeoUrl = $model->{$this->urlField} . '_';
            $model->{$this->urlField} = $newSeoUrl;
            // Run the validator again, because in the previous line, we changed
            // the value of adding a suffix
            $validator->validateAttribute($model, $this->urlField);
        }
    }

    /**
     * Verifies the maximum length of meta-strings, and if it exceeds the limit
     * - cuts to the maximum value
     *
     * @param string $key
     * @param string $lang
     */
    private function _applyMaxLength ($key, $lang) {
        $value = trim($this->_getMetaFieldVal($key, $lang));
        if ($key === self::TITLE_KEY) {
            $max = $this->_maxTitleLength;
        } elseif ($key === self::DESC_KEY) {
            $max = $this->_maxDescLength;
        } else {
            $max = $this->_maxKeysLength;
        }

        if (mb_strlen($value, $this->_encoding) > $max) {
            $value = mb_substr($value, 0, $max, $this->_encoding);
        }

        $this->_setMetaFieldVal($key, $lang, $value);
    }

    public function beforeSave () {
        $model = $this->owner;
        $this->beforeValidate();

        if (empty($this->metaField)) {
            // Unless specified meta-field, then we will not save
            return;
        }

        // Check all the SEO field and populate them with data, if specified by
        // the user - leave as is, if there is no - generate
        $this->_fillMeta();

        $meta = $model->{$this->metaField};

        // Save all data in a serialized form
        $model->{$this->metaField} = serialize($meta);
    }

    /**
     * Checks completion of all SEO:meta fields.
     * In their absence, they will be generated.
     */
    private function _fillMeta () {
        // Loop through all the languages available, it is often only one
        foreach ($this->languages as $lang) {
            // Loop through the meta-fields and fill them in the absence of
            // complete data
            foreach ($this->getMetaFields() as $meta_params_key => $meta_param_value_generator) {
                $meta_params_val = $this->_getMetaFieldVal($meta_params_key, $lang);
                if (empty($meta_params_val) && $meta_param_value_generator !== null) {
                    // Get value from the generator
                    $meta_params_val = $this->_getProduceFieldValue($meta_param_value_generator,
                        $lang);
                    $this->_setMetaFieldVal($meta_params_key, $lang,
                        SeoViewBehavior::normalizeStr($meta_params_val));
                }
                // We verify that the length of the string in the normal
                $this->_applyMaxLength($meta_params_key, $lang);
            }
        }
    }

    public function afterFind () {
        $model = $this->owner;

        if (!empty($this->metaField)) {
            // Unpack meta-params
            $meta = @unserialize($model->{$this->metaField});
            if (!is_array($meta)) {
                $meta = [];
            }

            $model->{$this->metaField} = $meta;
        }
    }

    /**
     * Returns an array of meta-fields.
     * As the value goes callback function
     *
     * @return callable[]
     */
    public function getMetaFields () {
        return [
            static::TITLE_KEY => $this->_titleProduceFunc,
            static::DESC_KEY => $this->_descriptionProduceFunc,
            static::KEYS_KEY => $this->_keysProduceFunc
        ];
    }

    /**
     * Returns the value of the $key SEO:meta for the specified $lang language
     *
     * @param string $key
     *            key TITLE_KEY, DESC_KEY or KEYS_KEY
     * @param string $lang
     *            language
     *
     * @return string|null
     */
    private function _getMetaFieldVal ($key, $lang) {
        $param = $key . '_' . $lang;
        $meta = $this->owner->{$this->metaField};

        return is_array($meta) && isset($meta[$param]) ? $meta[$param] : null;
    }

    /**
     * Sets the value of $key in SEO:meta on the specified $lang language
     *
     * @param string $key
     *            key TITLE_KEY, DESC_KEY or KEYS_KEY
     * @param string $lang
     *            language
     * @param string $value
     *            field value
     */
    private function _setMetaFieldVal ($key, $lang, $value) {
        $model = $this->owner;
        $param = $key . '_' . $lang;
        $meta = $model->{$this->metaField};
        if (!is_array($meta)) {
            $meta = [];
        }

        $meta[$param] = (string) $value;

        $model->{$this->metaField} = $meta;
    }

    /**
     * Returns the metadata for this model
     *
     * @param string|null $lang
     *            language, which requires meta-data
     *
     * @return array
     */
    public function getSeoData ($lang = null) {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }

        $buffer = [];

        if (!empty($this->metaField)) {
            // Check that all meta-fields were filled with the values
            $this->_fillMeta();
        }

        // If meta stored in a model, then refund the value of the model fields,
        // otherwise will generate data on the fly
        $getValMethodName = !empty($this->metaField) ? '_getMetaFieldVal' : '_getProduceFieldValue';

        foreach ($this->getMetaFields() as $meta_params_key => $meta_param_value_generator) {
            // Choosing what parameters are passed to the function get the
            // value: the name of the field or function-generator
            $getValMethodParam = !empty($this->metaField) ? $meta_params_key : $meta_param_value_generator;
            // Directly receiving the value of any meta-field of the model or
            // generate it
            $buffer[$meta_params_key] = $this->$getValMethodName($getValMethodParam, $lang);
        }

        return $buffer;
    }

    /**
     * Return instance of current behavior
     *
     * @return SeoModelBehavior $this
     */
    public function getSeoBehavior () {
        return $this;
    }

    /**
     * Returns the generated value for the meta-fields
     *
     * @param callable|string $produceFunc
     * @param string $lang
     *
     * @return string
     */
    private function _getProduceFieldValue ($produceFunc, $lang = null) {
        // Save current site language
        $originalLanguage = Yii::$app->language;
        // Change site language to $lang
        if (!empty($lang)) {
            Yii::$app->language = $lang;
        }

        $model = $this->owner;
        if (is_callable($produceFunc)) {
            $value = (string) call_user_func($produceFunc, $model, $lang);
        } else {
            $value = (string) $model->{$produceFunc};
        }

        // Restore original site language
        Yii::$app->language = $originalLanguage;

        return $value;
    }

    /**
     * Returns usable SEO name
     *
     * @param string $title
     * @param int $maxLength
     * @param bool $to_lower
     *
     * @return string
     */
    private function _getSeoName ($title, $maxLength = 255, $to_lower = true) {
        $trans = [
            "а" => "a",
            "б" => "b",
            "в" => "v",
            "г" => "g",
            "д" => "d",
            "е" => "e",
            "ё" => "yo",
            "ж" => "j",
            "з" => "z",
            "и" => "i",
            "й" => "i",
            "к" => "k",
            "л" => "l",
            "м" => "m",
            "н" => "n",
            "о" => "o",
            "п" => "p",
            "р" => "r",
            "с" => "s",
            "т" => "t",
            "у" => "y",
            "ф" => "f",
            "х" => "h",
            "ц" => "c",
            "ч" => "ch",
            "ш" => "sh",
            "щ" => "sh",
            "ы" => "i",
            "э" => "e",
            "ю" => "u",
            "я" => "ya",
            "А" => "A",
            "Б" => "B",
            "В" => "V",
            "Г" => "G",
            "Д" => "D",
            "Е" => "E",
            "Ё" => "Yo",
            "Ж" => "J",
            "З" => "Z",
            "И" => "I",
            "Й" => "I",
            "К" => "K",
            "Л" => "L",
            "М" => "M",
            "Н" => "N",
            "О" => "O",
            "П" => "P",
            "Р" => "R",
            "С" => "S",
            "Т" => "T",
            "У" => "Y",
            "Ф" => "F",
            "Х" => "H",
            "Ц" => "C",
            "Ч" => "Ch",
            "Ш" => "Sh",
            "Щ" => "Sh",
            "Ы" => "I",
            "Э" => "E",
            "Ю" => "U",
            "Я" => "Ya",
            "ь" => "",
            "Ь" => "",
            "ъ" => "",
            "Ъ" => ""
        ];
        // Replace the unusable characters on the dashes
        $title = preg_replace('/[^a-zа-яё\d_-]+/isu', '-', $title);
        // Remove dashes from the beginning and end of the line
        $title = trim($title, '-');
        $title = strtr($title, $trans);
        if ($to_lower) {
            $title = mb_strtolower($title, $this->_encoding);
        }
        if (mb_strlen($title, $this->_encoding) > $maxLength) {
            $title = mb_substr($title, 0, $maxLength, $this->_encoding);
        }

        // Return usable string
        return $title;
    }
}
