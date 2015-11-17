<?php
namespace nevmerzhitsky\seomodule\validators;

use yii\validators\Validator;

class MetaFieldValidator extends Validator {

    public function validateAttribute ($model, $attribute) {
        /** @var \nevmerzhitsky\seomodule\behaviors\SeoModelBehavior $seo */
        $seo = $model->getBehavior('seo');

        if (is_null($seo)) {
            return;
        }

        $meta = $model->$attribute;

        if (!is_array($meta)) {
            $this->addError($attribute, 'SEO meta field should be array.');
        } else {
            $keys = $seo::getMetaKeys();

            foreach ($meta as $lang => $subMeta) {
                foreach ($subMeta as $key => $value) {
                    if (!in_array($key, $keys)) {
                        $this->addError($attribute,
                            "Unknown field '{$key}' in SEO meta field of {$lang} language.");
                    }
                }
            }
        }
    }
}
