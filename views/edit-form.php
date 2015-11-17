<?php
/**
 *
 * @link Inspired by https://github.com/demisang/yii2-seo/
 */
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model \yii\db\ActiveRecord */
/* @var $seo nevmerzhitsky\seomodule\behaviors\SeoModelBehavior */

$seo = $model->getBehavior('seo');

if (empty($seo) || !$seo->userCanEdit) {
    return;
}
?>
<fieldset>
    <legend>SEO-oriented settings</legend>
<?php
if (!empty($seo->urlField)) {
    if ($form instanceof ActiveForm) {
        echo $form->field($model, $seo->urlField)->textInput();
    } else {
        echo '<div class="seo_row">';
        echo Html::activeLabel($model, $seo->urlField);
        echo Html::activeTextInput($model, $seo->urlField);
        echo Html::error($model, $seo->urlField);
        echo '</div>';
    }
}

foreach ($seo->languages as $lang) {
    foreach ($seo->getMetaFields() as $meta_field_key => $meta_field_generator) {
        $attr = $model->metaField . "[{$meta_field_key}_{$lang}]";
        $label = $seo::keyToLabel($meta_field_key);
        if (count($seo->languages) > 1) {
            $label .= ' (' . strtoupper($lang) . ')';
        }
        if ($form instanceof ActiveForm) {
            $input = $meta_field_key == $seo::DESC_KEY ? 'textarea' : 'textInput';
            echo $form->field($model, $attr)
                ->label($label)
                ->$input();
        } else {
            $input = $meta_field_key == $seo::DESC_KEY ? 'activeTextarea' : 'activeTextInput';
            echo '<div class="seo_row">';
            echo Html::activeLabel($model, $attr,
                [
                    'label' => $label
                ]);
            echo Html::$input($model, $attr);
            echo Html::error($model, $attr);
            echo '</div>';
        }
    }
}
?>
</fieldset>
