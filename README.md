# yii2-seomodule

Add ability to edit content of SEO-oriented HTML tags and attributes. Also add ability to configure redirection from any route to another with 301 status. And more SEO-oriented functions.

Highly inspired by https://github.com/demisang/yii2-seo and https://github.com/Amirax/yii2-seo-tools.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require nevmerzhitsky/yii2-seomodule "*"
```

or add

```json
"nevmerzhitsky/yii2-seomodule": "*"
```

to the require section of your `composer.json` file. Then run command

```code
php composer.phar update
```

After installation extension run migration:

```code
./yii migrate --migrationPath="@vendor/nevmerzhitsky/yii2-seomodule/migrations"
```

## Configuration of demisang/yii2-seo

In components configuration add the following:
```php
[
    'components' => [
        'seo' => [
            'class' => 'nevmerzhitsky\seomodule\Meta'
        ],
        'view' => [
            'as seo' => [
                'class' => 'nevmerzhitsky\seomodule\SeoViewBehavior',
            ]
        ],
    ],
    ...
];
```

And add SEO extension to bootstrap:
```php
'bootstrap' => ['log', 'seo']
```

In model file add seo model behavior:
```php
public function behaviors()
{
    $it = $this;

    return [
        'seo' => [
            'class' => 'nevmerzhitsky\seomodule\SeoModelBehavior',
            'title' => [
                'produceFunc' => 'title',
                'produceMaxLength' => 150,
                'overrideByDb' => false
            ],
            'descriptionProduceFunc' => 'short_desc',
            'keysProduceFunc' => function ($model) {
                /* @var $model self|\yii\db\ActiveRecord */
                return $model->title . ', tag1, tag2';
            },
            'metaField' => 'seo_meta',
            'userCanEdit' => Yii::$app->has('user') && Yii::$app->user->can(User::ROLE_ADMIN),
            // 'languages' => 'ru',
            'urlField' => 'seo_url',
            'urlProduceField' => 'title',
            'controllerClassName' => '\frontend\controllers\PostController',
            'uniqueUrlFilter' => function ($query) use ($it) {
                /* @var $query \yii\db\Query */
                $query->andWhere(['category_id' => $it->category_id]);
            },
        ],
    ];
}
```

In main layout:
```php
<head>
    <title><?php echo Html::encode(Yii::$app->seo->title); ?></title>
<?php $this->head(); ?>
    ...
</head>
```

## Usage

In a controller action for the model ("view" for example):
```php
Yii::$app->seo->registerModel($model);
```

You can register several models in one action. If these models have 'seo' behavior, then SEO data will combined from all of them in registration order.

In admin/manager site you can add fields for editing SEO data of a model ("_form.php" template):
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

## Configuration and using of the Amirax/yii2-seo-tools

### SEO Meta

Extension will automatically load the correct row from the database using the currently
running and params.You can optionally override data by specifying them in a parameter array
```php
Yii::$app->seo->title = 'Page title';
Yii::$app->seo->metakeys = 'seo,yii2,extension';
Yii::$app->seo->metadesc = 'Page meta description';
Yii::$app->seo->tags['og:type'] = 'article';
```

You can set the templates for tags. For example:
```php
Yii::$app->seo->setVar('USER_NAME', 'Smith');
Yii::$app->seo->tags['og:title'] = 'Hello %USER_NAME%';
```

Default variables:
* %HOME_URL%       - Homepage url
* %CANONICAL_URL%  - Canonical URL for current page
* %LOCALE%         - Site locale

### SEO Redirect
For enabling SEO Redirect add to configuration file 
```php
'errorHandler' => [
    'class' => 'nevmerzhitsky\seomodule\Redirect',
],
```
