# Codeigniter SOM (Um simples ORM do monstro)

Criei a classe afim de evitar a escrita e reescrita de métodos comuns de consulta a base para cada modelo de entidade do seu projeto.

Através de uma convenção estabelecida para o banco de dados e modelos é possível trazer os registros do banco para objetos sem muito esforço =)

Há ótimos orm's no mercado, mas queria algo simples, enxuto, que coubesse bem no codeigniter.

## Prerequisites

* [Codeigniter 3](https://codeigniter.com/) com a library database carregada;

### Database

* Tabelas devem ser nomeadas no plural e em letras minúsculas;
* Colunas devem ser nomeadas no singular e em letras minúsculas;
* Chaves primárias devem ser nomeadas como `id`;
* Chaves estranageiras devem ser nomeadas como `id_{nome-da-tabela-estrangeira}` no singular;

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

Tendo como exemplo a tabela `users` acima, nosso modelo deve ser nomeado no singular e deve conter como atributo os campos da tabela com exceção do campo id que é herdado da classe MY_Model, ex:

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

#### Mapping the relationship one to many

Agora precisamos informar que tipo de relação nosso usuário tem com cidade. Nas duas tabelas apresentadas percebemos que o relacionemento é que o usuário pertence a uma cidade e uma cidade possui vários usuários.

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

`/application/model/City_model`
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

Mapeamos em um atributo protected via doc params que, um usuário `has_one` cidade e uma cidade `has_many` usuários. Para o tipo de relacionamento *has_many* de um atributo, é possível definir por qual campo será ordenado o array de entidades quando acessado, passando o parâmetro `@order`

#### Mapping the relationship many to many
// TODO: Documentar sobre o tipo de relacionamento many_to_many;

## In Action

Uma vez mapeado seus modelos, podemos acessar/salvar os atributos do objeto na base de dados sem esforço.

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

### ::get($id)

Retorna uma instancia da entidade pelo seu id.

* $id `int` `required`

```php
User_model::get(1);
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
// TODO: Documentar caso precise criar suas próprias consultas na model e usufruir do SOM. 
