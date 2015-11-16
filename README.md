yii2-seomodule
========
Add ability to edit content of SEO-oriented HTML tags and attributes. Also add ability to configure redirection from any route to another with 301 status. And more SEO-oriented functions.

Highly inspired by https://github.com/demisang/yii2-seo and https://github.com/Amirax/yii2-seo-tools.

Installation
------------
Add to composer.json in your project
```json
{
    "require":
    {
        "nevmerzhitsky/yii2-seomodule": "dev-master"
    }
}
```
then run command
```code
php composer.phar update
```
Configuration
-------------
frontend/config/main.php
```php
return [
    'components' => [
        'view' => [
            'as seo' => [
                'class' => 'nevmerzhitsky\seomodule\SeoViewBehavior',
            ]
        ],
    ],
];
```

In model file add seo model behavior:
```php
public function behaviors()
{
    $it = $this;

    return [
        'seo' => [
            'class' => 'nevmerzhitsky\seomodule\SeoModelBehavior',
            'seoConfig' => [
                'urlField' => 'seo_url',
                'urlProduceField' => 'title',
                'titleProduceFunc' => 'title',
                'descriptionProduceFunc' => 'short_desc',
                'keysProduceFunc' => function ($model) {
                        /* @var $model self|\yii\db\ActiveRecord */
                        return $model->title . ', tag1, tag2';
                    },
                'metaField' => 'seo_meta',
                'clientChange' => Yii::$app->has('user') && Yii::$app->user->can(User::ROLE_ADMIN),
                // 'languages' => 'ru',
                'controllerClassName' => '\frontend\controllers\PostController',
                'uniqueUrlFilter' => function ($query) use ($it) {
                        /* @var $query \yii\db\Query */
                        $query->andWhere(['category_id' => $it->category_id]);
                    },
            ],
        ],
    ];
}
```

PHPdoc for model:
```php
/**
 * @property array $seoData
 * @method array getSeoData($lang = null) Metadata for this model
 * @method \nevmerzhitsky\seomodule\SeoModelBehavior getSeoBehavior()
 */
```
In main layout:
```php
<head>
    <?php echo $this->renderMetaTags(); ?>
    ...
</head>
```

Usage
-----
In "view" template for a model:
```php
// Setup title and meta tags of the current page by the model.
$this->setSeoData($model->getSeoBehavior());
// Set robots meta-tag content of the page.
$this->setMetaRobots('noindex, nofollow');
```
Or in a controller:
```php
Yii::$app->view->setSeoData($model->getSeoBehavior());
Yii::$app->view->setMetaRobots('noindex, nofollow');
```

Render SEO:url and SEO:meta fields in the "_form.php" file:
```php
<?php
$this->beginContent('@app/vendor/nevmerzhitsky/yii2-seomodule/views/edit-form.php',
    [
        'model' => $model,
        'form' => $form
    ]);
$this->endContent();
?>
```