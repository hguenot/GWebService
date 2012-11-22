# GWebService

GWebService is a Web Service extension for [YII Framework](http://www.yiiframework.com) based on [NuSoap library](http://sourceforge.net/projects/nusoap/).

It contains :

* A Web Service Extension :
** `GSoapServer` : A component used to publish methods through WSDL definition.
** `GSoapClient` : An application component that wraps FTP component.
* A command tool :
** `wsdl` : Generate a custom client using WSDL definition.


## Installation

As many YII extension, you have to unzip archive under a subfolder of your extensions folder.

__**Subfolder must be named GWebService.**__

For command line tool, you have to unzip archive under `commands` folder.

## Usage

### GWebService extension

We indicate only basic usage. More samples could be found on [GWebService extension website](http://yii.guenot.info/index.php?r=gws). 

* Publish a web service 

Web Service publishing is based on PHP doc comment. Document correctly you methode and just insert the `@soap` tag in any method description to publish it.
The other thing to do : define a `GSoapServerAction`action in you controller.

```php

class WsController extends CController {

  public function actions() {
    return array(
      'service'=>array(
        'class'    => 'ext.GWebService.GSoapServerAction',
      ),
    );
  }
  
  /**
   * @param string $str Your name
   * @return string Hello you !
   * @soap
   */
  public function sayHello($str) {
    return 'Hello ' . $str . '!';
  }
  
}

```

The WSDL could be accessed using `http://your.domain.com/index.php?r=my/service` and method could be invoked using URL `http://your.domain.com/index.php?r=my/service&ws=1`.

* Invoking remote method 

You can invoke remote method by instanciatin GSoapClient. Next invoke `call` method on the created object to invoke remote method.

```php

$client = Yii::createComponent(array(
  'class' => 'ext.GWebService.GSoapClient',
  'wsdlUrl' => 'http://your.domain.com/index.php?r=my/service'
));

// remote method parameters are passed as an array
$client->call('WsController.sayHello', array('Web Service'));

```

### GWebService command tool

