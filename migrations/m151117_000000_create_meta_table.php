<?php
use yii\db\Migration;

class m151117_000000_create_meta_table extends Migration {

    public function safeUp () {
        $this->createTable('{{%seo_meta}}',
            [
                'id' => $this->primaryKey(),
                'route' => $this->string(),
                'params' => $this->string(),
                'title' => $this->string(),
                'metakeys' => $this->string(),
                'metadesc' => $this->string(),
                'tags' => $this->string(),
                'robots' => $this->integer()->notNull()->defaultValue(0)
            ]);
        $this->createIndex('idx_route', '{{%seo_meta}}', 'route', true);
        $this->createIndex('idx_params', '{{%seo_meta}}', 'params', true);

        $this->insert('{{%seo_meta}}',
            [
                'route' => '-',
                'title' => '',
                'metakeys' => '',
                'metadesc' => '',
                'tags' => json_encode(
                    [
                        'og:type' => 'website',
                        'og:url' => '%CANONICAL_URL%'
                    ]),
                'robots' => 0
            ]);
    }

    public function safeDown () {
        $this->dropTable('{{%seo_meta}}');
    }
}
