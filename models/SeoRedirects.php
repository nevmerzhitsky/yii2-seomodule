<?php
namespace nevmerzhitsky\seomodule\models;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "seo_redirects".
 *
 * @property integer $id
 * @property string $old_url
 * @property string $new_url
 * @property string $status
 * @package nevmerzhitsky\seomodule
 */
class SeoRedirects extends ActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName () {
        return '{{%seo_redirects}}';
    }
}
