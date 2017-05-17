# Codeigniter SOM (*A simple O.R.M. do monstro*)

Criei a classe afim de evitar a escrita e reescrita de métodos comuns de consulta a base, para cada model do projetos.

Através de uma convenção estabelecida para o banco de dados e models é possível trazer os registros do banco para objetos sem muito esforço =)

Há ótimos orm's no mercado, mas precisava de algo muito simples e enxuto e que servisse bem no codeigniter.

## Prerequisites

* [Codeigniter 3](https://codeigniter.com/) com a library database carregada;

### Database

* Tabelas devem ser nomeadas no plural e em letras minúsculas;
* Colunas devem ser nomeadas no singular e em letras minúsculas;
* Chaves primárias devem ser nomeadas como `id`;
* Chaves estranageiras devem ser nomeadas como `id_<nome-da-tabela-estrangeira>` no singular;

Para ilustrar, segue um exemplo de como seria uma tabela de usuários seguindo a convenção:

Table `users`:
```
+---------+------------------+------+-----+---------+----------------+
| Field   | Type             | Null | Key | Default | Extra          |
+---------+------------------+------+-----+---------+----------------+
| id      | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
| name    | varchar(45)      | NO   |     | NULL    |                |
| email   | varchar(60)      | YES  |     | NULL    |                |
| id_city | int(11) unsigned | YES  | MUL | NULL    |                |
+---------+------------------+------+-----+---------+----------------+
```

## Installing

Basta colocar a classe `MY_Model.php` em /application/core

### Mapping table / model

Tendo como exemplo a tabela `users` acima, nossa model deve ser nomeada no singular e deve conter como atributos, os campos da tabela com exceção do campo id que é herdado da classe MY_Model, ex:

`/application/models/User_model.php`
```php
class User_model extends MY_Model {
    public $name;
    public $email;
    public $id_city;
}
```

A seguir, segue exemplo da tabela `cities` no qual users tem relação:

```
+-------+------------------+------+-----+---------+----------------+
| Field | Type             | Null | Key | Default | Extra          |
+-------+------------------+------+-----+---------+----------------+
| id    | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
| name  | varchar(45)      | NO   |     | NULL    |                |
+-------+------------------+------+-----+---------+----------------+
```

E sua model: `/application/model/City_model`
```php
class City_model extends MY_Model {
    public $name;
}
```

Mapeamos para a model todos os campos da tabela como atributos públicos. Atributos públicos da model serão salvos em sua tabela correspondente se os nomes dos atributos e colunas foram iguais.

#### One to many relationship

Agora precisamos informar que tipo de relação nosso usuário tem com cidade. Nas duas tabelas apresentadas percebemos que o usuário pertence a uma cidade e uma cidade possui vários usuários.

Vamos informar as models sobre o relacionamento:

`/application/models/User_model.php`
```php
class User_model extends MY_Model {
    (...)
    
    /**
     * @cardinality has_one
     * @class City_model
     */
    protected $city
}
```

`/application/models/City_model`
```php
class City_model extends MY_Model {
    (...)
    
    /**
     * @cardinality has_many
     * @class User_model
     * @order name
     */
    protected $users;
}
```

Mapeamos em um atributo protected via doc params que, um usuário `has_one` cidade e uma cidade `has_many` usuários. Para o tipo de relacionamento *has_many* de um atributo, é possível definir por qual campo será ordenado o array de entidades quando acessado, passando o parâmetro `@order`.

#### Many to many relationship

No caso do mapeamento many to many, temos uma tabela adicional para guardar a relação entre duas entidades. Neste exemplo um usuário possui muitos papéis e um papel pode possuir muitos usuários.

Segue abaixo o exemplo da tabela `roles`:

```
+-------+------------------+------+-----+---------+----------------+
| Field | Type             | Null | Key | Default | Extra          |
+-------+------------------+------+-----+---------+----------------+
| id    | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
| name  | varchar(45)      | NO   |     | NULL    |                |
+-------+------------------+------+-----+---------+----------------+
```

E a tabela adicional `users_roles` para mapaear o relacionemento entre essas duas entidades:

```
+---------+------------------+------+-----+---------+-------+
| Field   | Type             | Null | Key | Default | Extra |
+---------+------------------+------+-----+---------+-------+
| id_user | int(11) unsigned | NO   | PRI | NULL    |       |
| id_role | int(11) unsigned | NO   | PRI | NULL    |       |
+---------+------------------+------+-----+---------+-------+
```

Para o mapeamento na model, adicionamos um atributo em User_model para referenciar Role_model na cardinalidade `has_many` e um atributo em Role_model `has_many` para referenciar User_model.

`/application/models/User_model.php`
```php
class User_model extends MY_Model {
    (...)
    
    /**
     * @cardinality has_one
     * @class City_model
     */
    protected $city

    /**
     * @cardinality has_many
     * @class Role_model
     * @table users_roles
     * @order name
     */
    protected $roles;
}
```

`/application/models/Role_model.php`
```php
class Role_model extends MY_Model {

    public $name;

    /**
     * @cardinality has_many
     * @class User_model
     * @table users_roles
     */
    protected $users;
}
```

O doc param `@table` é necessário aqui para informar a tabela adicional que irá guardar a relação many to many entre usuário e papel.

## In Action

Uma vez mapeado suas models, podemos acessar/salvar os atributos do objeto na base de dados sem esforço.

`application/controllers/Welcome.php`
```php
(...)
public function index()
{
    $this->load->model('user_model');
    
    $user = User_model::get(1); // gets user id = 1
    $user->name = 'Rafael';
    $user->save(); // updates the user since user id = 1 is already stored in database
    
    // you can use the chain method
    User_model::get(1)
        ->fill([
            'name' => 'Rafael',
            'email' => 'rafael@turtleninja.com'
        ])
        ->save();
    
    // you can show which city the user id = 1 bellongs
    var_dump(User_model::get(1)->city);
    
    // You can retrieve all users from database
    User_model::find();
    // You can retrieve all users from database where name starts with "R" ordered by name descending
    User_model::find(['name like' => 'R%'], 'name desc');
    // and age > 18
    User_model::find(['name like' => 'R%', 'age >=' => 18 ], 'name desc');
    // and is_deleted = FALSE
    User_model::find(['name like' => 'R%', 'age >=' => 18, 'is_deleted' => FALSE]);
    // paged? sure
    User_model::find([
        'name like' => 'R%', 
        'age >=' => 18, 
        'is_deleted' => FALSE], 'name asc', TRUE, 20, 0); // limit 20 offset 0

    // store a fresh user in database
    $user = new User_model();
    $user->name = 'Michelangelo';
    $user->email = 'michelangelo@turtleninja.com';
    $user->save();
    
    // you can use the chain method too
    $user = new User_model();
    $user->fill($this->input->post())->save();
    
    // same as city
    $this->load->model('city_model');
    var_dump(City_model::get(1)->users) // array of User_model's object
}
```

## Methods

### ::find($filters, $order_by, $paged, $limit, $offset)

Retorna um array de entidades de acordo com os parâmetros passados.

* $filters `Array` `optional` default `array()`  
Goes on $this->db->where($filters)
* $order_by `string` `optional` default `NULL`
* $paged `boolean` `optional` default `FALSE`
* $limit `int` `optional`default `0`
* $offset `int` `optional` default `0`

```php
User_model::find();
```

```
array (size=4)
  0 => 
    object(User_model)[24]
      public 'name' => string 'Rafael' (length=6)
      public 'email' => string 'rafael@turtleninja.com' (length=22)
      public 'id_city' => string '1' (length=1)
      protected 'city' => null
      protected 'roles' => null
      public 'id' => string '4' (length=1)
      protected '_is_persisted' => boolean true
  1 => 
    object(User_model)[25]
      public 'name' => string 'Michelangelo' (length=12)
      public 'email' => string 'michelangelo@turtleninja.com' (length=28)
      public 'id_city' => string '1' (length=1)
      protected 'city' => null
      protected 'roles' => null
      public 'id' => string '5' (length=1)
      protected '_is_persisted' => boolean true

(...)
```

```php
User_model::find([], NULL, TRUE, 2, 0);
```

```
array (size=3)
  'total' => int 4
  '_links' => 
    array (size=3)
      'self' => 
        array (size=1)
          'href' => string '?limit=2&offset=0' (length=17)
      'next' => 
        array (size=1)
          'href' => string '?limit=2&offset=2' (length=17)
      'last' => 
        array (size=1)
          'href' => string '?limit=2&offset=2' (length=17)
  'items' => 
    array (size=2)
      0 => 
        object(User_model)[22]
          public 'name' => string 'Rafael' (length=6)
          public 'email' => string 'rafael@turtleninja.com' (length=22)
          public 'id_city' => string '1' (length=1)
          protected 'city' => null
          protected 'roles' => null
          public 'id' => string '4' (length=1)
          protected '_is_persisted' => boolean true
      1 => 
        object(User_model)[23]
          public 'name' => string 'Michelangelo' (length=12)
          public 'email' => string 'michelangelo@turtleninja.com' (length=28)
          public 'id_city' => string '1' (length=1)
          protected 'city' => null
          protected 'roles' => null
          public 'id' => string '5' (length=1)
          protected '_is_persisted' => boolean true
```

### ::get($id)

Retorna uma instancia da entidade pelo seu id.

* $id `int` `required`

```php
User_model::get(4);
```

```
object(User_model)[21]
  public 'name' => string 'Rafael' (length=6)
  public 'email' => string 'rafael@turtleninja.com' (length=22)
  public 'id_city' => string '1' (length=1)
  protected 'city' => null
  protected 'roles' => null
  public 'id' => string '4' (length=1)
  protected '_is_persisted' => boolean true
```

### ::count($filters, $distinct)

Retorna um inteiro de quantos registros existem na tabela de acordo com os parâmetros passados.

* $filters `Array` `optional` default `array()`
* $distinct `Array` `optional` default `array()`

```php
User_model::count() //8
User_model::count(['name' => 'Rafael']) //1
```

### ->fill($properties)

Preenche uma entidade com os valores passados por parâmetro. Esse método deve receber um array no formato chave => valor, onde o nome da chave, seja igual ao atributo do objeto que deseja alterar o valor.

* $properties `Array` `required`

```php
$user = new User_model();
$user->fill([
    'name' => 'Leonardo',
    'email' => 'leonardo@turtleninja.com'
]);
```

### ->delete()

Remove uma entidade do banco de dados.

```php
User_model::get(1)->delete(); // or
//---------------------------
$user = User_model::get(1);
$user->delete();
```

### ->save()

Salva uma entidade no banco de dados.

```php
$user = new User_model();
$user->name = 'Michelangelo';
$user->id_city = 'New York';
$user->save(); // insert
//---------------------------
$user = User_model::get(1);
$user->name = 'Michelangelo';
$user->id_city = 'New York';
$user->save() // update
```

## Extending
// TODO: ... 
