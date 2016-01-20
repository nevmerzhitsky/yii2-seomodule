<?php
use yii\db\Migration;

class m151117_000001_create_redirect_table extends Migration {

    public function safeUp () {
        $this->createTable('{{%seo_redirects}}',
            [
                'id' => $this->primaryKey(),
                'old_url' => $this->string()->notNull(),
                'new_url' => $this->string()->notNull()->defaultValue('/'),
                'status' => $this->integer()->notNull()->defaultValue(301)
            ]);
        $this->createIndex('idx_old_url', '{{%seo_redirects}}', 'old_url', true);
    }

    public function safeDown () {
        $this->dropTable('{{%seo_redirects}}');
    }
}
