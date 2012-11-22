# GWebService

GWebService is a Web Service extension for [YII Framework](http://www.yiiframework.com) based on [NuSoap library](http://sourceforge.net/projects/nusoap/).

It contains :

* A Web Service Extension :
** `GSoapServer` : A component used to publish methods through WSDL definition.
** `GSoapClient` : An application component that wraps FTP component.
* A command tool :
** `wsdl` : Generate a custom client using WSDL definition.


## Installation

GWebService distribution contains 2 folders : 

* `command` : which contains command line tools. Copy it under commands
* `extension` : which contains GWebService extension folder. Copy it under extensions folder.

## Usage

### GWebService extension

We indicate only basic usage. More samples could be found on [GWebService extension website](http://yii.guenot.info/index.php?r=gws). 

* Publish a web service 

Web Service publishing is based on PHP doc comment. Document correctly you methode and just insert the `@soap` tag in any method description to publish it.
The other thing to do : define a `GSoapServerAction`action in you controller.

```php

class MyController extends CController {

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
$client->call('MyController.sayHello', array('Web Service'));

```

### GWebService command tool

Command line tools generates specific client based on wsdl description by defining methods which map wsdl operation.

```shell
protected/yiic wsdl http://your.domain.com/index.php?r=my/service
```

This will generate class under `components` folder `MyControllerClient` having a method `sayHello` taking a string argument.

```php

class MyControllerClient extends GSoapClient {

    public function sayHello($str) {
        return $this->call('MyController.sayHello', array($str));
    }

}

```

Just instanciate new object and call `sayHello` method.

