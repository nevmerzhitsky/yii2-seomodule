<?php
namespace nevmerzhitsky\seomodule\models;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "seo_meta".
 *
 * @property integer $id
 * @property string $route
 * @property string $params
 * @property string $title
 * @property string $metakeys
 * @property string $metadesc
 * @property string $tags
 * @property integer $robots
 * @package nevmerzhitsky\seomodule
 */
class SeoMeta extends ActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName () {
        return '{{%seo_meta}}';
    }
}
