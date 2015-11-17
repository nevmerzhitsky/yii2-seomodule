<?php
/**
 * @link Inspired by https://github.com/demisang/yii2-seo/
 */
namespace nevmerzhitsky\seomodule\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;
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

    /** @var string[][] Saved actions of controllers for SEO:url stop list */
    private static $_controllersActions = [];

    /**
     *
     * @return string[]
     */
    public static function getMetaKeys () {
        return [
            static::TITLE_KEY,
            static::DESC_KEY,
            static::KEYS_KEY
        ];
    }

    public static function keyToLabel ($key) {
        static $map = [
            self::TITLE_KEY => 'Title',
            self::DESC_KEY => 'Description',
            self::KEYS_KEY => 'Keywords'
        ];

        return $map[$key];
    }

    /**
     *
     * @param \yii\base\Model $model
     * @return callback @TODO Convert to standalone validator.
     */
    public static function metaFieldValidator ($model) {
        return function  ($attribute, $params) use( $model) {
            $meta = $model->{$attribute};

            if (!is_array($meta)) {
                $model->addError($attribute, 'SEO meta field should be array.');
            } else {
                $keys = static::getMetaKeys();

                foreach ($meta as $lang => $subMeta) {
                    foreach ($subMeta as $key => $value) {
                        if (!in_array($key, $keys)) {
                            $model->addError($attribute,
                                "Unknown field '{$key}' in SEO meta field of {$lang} language.");
                        }
                    }
                }
            }
        };
    }

    /** @var string The name of the field responsible for SEO:url */
    public $urlField;

    /**
     *
     * @var string|callable The name of the field which will form the SEO:url,
     *      or function,
     */
    public $urlProduceField = 'title';

    public $title = [
        /** @var string|callable PHP-expression that generates field SEO:title */
        'produceFunc' => null,
        /** @var integer The maximum length of the field Title */
        'produceMaxLength' => 70,
        /** @var boolean If true then a produced value will fully overridden by a content from DB */
        'overrideByDb' => true
    ];

    /** @var string|callable PHP-expression that generates field SEO:desciption */
    public $descriptionProduceFunc;

    /** @var string|callable PHP-expression that generates field SEO:keywords */
    public $keysProduceFunc;

    /**
     *
     * @var string The name of the resulting field, which will be stored in a
     *      JSON form of all SEO-parameters
     */
    public $metaField;

    /** @var boolean|callable Whether to allow the user to change the SEO-data */
    public $userCanEdit = true;

    /** @var integer The maximum length of the field SEO:url */
    public $maxUrlLength = 70;

    /** @var integer The maximum length of the field Description */
    public $maxDescLength = 130;

    /** @var integer The maximum length of the field Keywords */
    public $maxKeysLength = 150;

    /** @var array Forbidden for use SEO:url names */
    public $stopNames = [
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
    public $controllerClassName = '';

    /** @var boolean Is it necessary to use only lowercase when generating seo_url */
    public $toLowerSeoUrl = true;

    /** @var Query Additional criteria when checking the uniqueness of seo_url */
    public $uniqueUrlFilter;

    /** @var string encoding site */
    public $encoding = 'UTF-8';

    public function events () {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind'
        ];
    }

    public function attach ($owner) {
        parent::attach($owner);

        $this->languages = (array) $this->languages;
        // If there was not passed any language - we use only one system language.
        if (!count($this->languages)) {
            $this->languages = [
                Yii::$app->language
            ];
        }

        // if the current user can see and edit SEO-data model
        if (is_callable($this->userCanEdit)) {
            $this->userCanEdit = call_user_func($this->userCanEdit, $owner);
        }

        // Determine the controller and add it actions to the seo url stop list
        if (!empty($this->urlField) && !empty($this->controllerClassName)) {
            if (isset(static::$_controllersActions[$this->controllerClassName])) {
                // Obtain the previously defined controller actions
                $buffer = static::$_controllersActions[$this->controllerClassName];
            } else {
                // Get all actions of controller
                $reflection = new \ReflectionClass($this->controllerClassName);
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
                static::$_controllersActions[$this->controllerClassName] = $buffer;
            }

            // Merge controller actions with actions from config behavior
            $this->stopNames = array_unique(array_merge($this->stopNames, $buffer));
        }
    }

    public function beforeValidate () {
        $model = $this->owner;

        // Validate meta fields.
        if (!empty($this->metaField)) {
            // Loop through all the languages available, it is often only one
            foreach ($this->languages as $lang) {
                // Loop through the fields and cut the long strings to set allowed length
                foreach ($this->getMetaFields() as $key => $valueGenerator) {
                    $this->_applyMaxLength($key, $lang);
                }
            }
        }

        $this->_validateUrlField();
    }

    /**
     * Validate SEO URL and ensure its uniqueness.
     */
    private function _validateUrlField () {
        if (empty($this->urlField)) {
            // If do not need to work with SEO:url, then skip further work
            return;
        }

        $model = $this->owner;

        // Add UNIQUE validator for SEO:url field
        $validator = Validator::createValidator(UniqueValidator::className(), $model,
            $this->urlField, [
                'filter' => $this->uniqueUrlFilter
            ]);

        // If SEO: url is not filled by the user, then generate its value
        $urlFieldVal = trim((string) $model->{$this->urlField});
        if ($urlFieldVal === '') {
            $urlFieldVal = $this->_getProduceValue($this->urlProduceField);
        }
        // Transliterated string and remove from it the extra characters
        $seoUrl = $this->_getSeoName($urlFieldVal, $this->maxUrlLength, $this->toLowerSeoUrl);

        // If there is a match with banned names, then add to the url underbar
        // to the end
        while (in_array($seoUrl, $this->stopNames)) {
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
        $value = trim($this->_getDbValue($key, $lang));

        if ($key === self::TITLE_KEY) {
            // @TODO Call only for produce content.
            $max = $this->title['produceMaxLength'];
        } elseif ($key === self::DESC_KEY) {
            $max = $this->maxDescLength;
        } else {
            $max = $this->maxKeysLength;
        }

        if (mb_strlen($value, $this->encoding) > $max) {
            $value = mb_substr($value, 0, $max, $this->encoding);
        }

        $this->_setDbValue($key, $lang, $value);
    }

    public function beforeSave () {
        $model = $this->owner;
        $this->beforeValidate();

        // Unless specified meta-field, then we will not save
        if (empty($this->metaField)) {
            return;
        }

        $meta = $model->{$this->metaField};

        // Save all data in a JSON form.
        $model->{$this->metaField} = json_encode($meta);
    }

    /**
     * Checks completion of all SEO:meta fields.
     * In their absence, they will be generated.
     */
    private function _fillMeta () {
        foreach ($this->languages as $lang) {
            // Loop through the meta-fields and fill them in the absence of complete data.
            foreach ($this->getMetaFields() as $key => $valueGenerator) {
                $value = $this->_getDbValue($key, $lang);
                if (empty($value) && $valueGenerator !== null) {
                    // Get value from the generator
                    $value = $this->_getProduceValue($valueGenerator, $lang);
                    $this->_setDbValue($key, $lang, SeoViewBehavior::normalizeStr($value));
                }
                // We verify that the length of the string in the normal
                $this->_applyMaxLength($key, $lang);
            }
        }
    }

    public function afterFind () {
        $model = $this->owner;

        if (!empty($this->metaField)) {
            // Unpack meta-params
            $meta = json_decode($model->{$this->metaField}, true);
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
            static::TITLE_KEY => $this->title['produceFunc'],
            static::DESC_KEY => $this->descriptionProduceFunc,
            static::KEYS_KEY => $this->keysProduceFunc
        ];
    }

    /**
     * Returns the value of the $key SEO:meta for the specified $lang language
     *
     * @param string $key
     *            key TITLE_KEY, DESC_KEY or KEYS_KEY
     * @param string $lang
     *            language
     * @return string|null
     */
    private function _getDbValue ($key, $lang) {
        $meta = $this->owner->{$this->metaField};

        if (!is_array($meta) || !isset($meta[$lang]) || !isset($meta[$lang][$key])) {
            return null;
        }

        return $meta[$lang][$key];
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
    private function _setDbValue ($key, $lang, $value) {
        $model = $this->owner;
        $meta = $model->{$this->metaField};
        if (!is_array($meta)) {
            $meta = [];
        }

        if (!isset($meta[$lang])) {
            $meta[$lang] = [];
        }

        $meta[$lang][$key] = (string) $value;

        $model->{$this->metaField} = $meta;
    }

    /**
     * Returns the metadata for this model
     *
     * @param string|null $lang
     *            language, which requires meta-data
     * @return array
     */
    public function getSeoData ($lang = null) {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }

        // Check that all meta-fields were filled with the values.
        if (!empty($this->metaField)) {
            $this->_fillMeta();
        }

        $buffer = [];
        foreach ($this->getMetaFields() as $key => $valueGenerator) {
            // If meta stored in a model, then refund the value of the model fields, otherwise will
            // generate data on the fly
            if (!empty($this->metaField)) {
                $buffer[$key] = $this->_getDbValue($key, $lang);
            } else {
                $buffer[$key] = $this->_getProduceValue($valueGenerator, $lang);
            }
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
    private function _getProduceValue ($produceFunc, $lang = null) {
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
     * @param bool $toLower
     * @return string
     */
    private function _getSeoName ($title, $maxLength = 255, $toLower = true) {
        $trans = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'i',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'y',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sh',
            'ы' => 'i',
            'э' => 'e',
            'ю' => 'u',
            'я' => 'ya',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'J',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'I',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'Y',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sh',
            'Ы' => 'I',
            'Э' => 'E',
            'Ю' => 'U',
            'Я' => 'Ya',
            'ь' => '',
            'Ь' => '',
            'ъ' => '',
            'Ъ' => ''
        ];
        // Replace the unusable characters on the dashes
        $title = preg_replace('/[^a-zа-яё\d_-]+/isu', '-', $title);
        // Remove dashes from the beginning and end of the line
        $title = trim($title, '-');
        $title = strtr($title, $trans);
        if ($toLower) {
            $title = mb_strtolower($title, $this->encoding);
        }
        if (mb_strlen($title, $this->encoding) > $maxLength) {
            $title = mb_substr($title, 0, $maxLength, $this->encoding);
        }

        // Return usable string
        return $title;
    }
}
