# Codeigniter SOM (*A simple O.R.M. do monstro*)

I made this to avoid writing and rewriting common query methods for each model you have in your project.

Through a convention established between the database and your models is possible to fetch information from the database to your objects without effort. =)

There is a lot of great orms out there, but I built that, thinking to being very simple, light and fit well in the codeigniter.

## Prerequisites

* [Codeigniter 3](https://codeigniter.com/) with the database library loaded;

### Database

* Tables should be named in plural and lower case;
* Columns must be named in singular and lower case;
* Primary keys must be named as `id`;
* Foreign keys must be named as `id_<name_of_foreign_table>` in singular;

To illustrate that, here is an example how would looks like a table of users following the convention:

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

Just put the class `MY_Model.php` in */application/core*

### Mapping table / model

Taking the `users` table as an example, our model must be named in the singular and must contains as yours attributes, the table fields that you want with exception of the *"id"* field, that is inherited from the MY_Model class. eg.:

`/application/models/User_model.php`
```php
class User_model extends MY_Model {
    public $name;
    public $email;
    public $id_city;
}
```

Following is a sample of the table `cities` which an *user* is related with:

```
+-------+------------------+------+-----+---------+----------------+
| Field | Type             | Null | Key | Default | Extra          |
+-------+------------------+------+-----+---------+----------------+
| id    | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
| name  | varchar(45)      | NO   |     | NULL    |                |
+-------+------------------+------+-----+---------+----------------+
```

And your model following the convention: `/application/model/City_model`
```php
class City_model extends MY_Model {
    public $name;
}
```

We map to the model all the table fields we want as public attributes. Public attributes will be saved in your corresponding table if their names are *equals to the table fields*.

#### One to many relationship

Now we need to inform what kind of relation our user has with city. In the two samples above we realize that one user belongs a city and one city has many users, characterizing the type *one to many* relationship.

Lets inform models about that kind of relationhip:

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

We map on a protected attribute with *doc params* that one user `has_one` city and one city `has_many` users. It is possible to define by which field of the related table the result will be sorted when the attribute is accessed, informing `@order` in *doc param*.

#### Many to many relationship

In the *many to many* relationship case , we have an additional table to store the relation between two entities. In this example one *user* has many *roles* and one *role* can be associate to many *users*.

Here is an example of the `roles` table:

```
+-------+------------------+------+-----+---------+----------------+
| Field | Type             | Null | Key | Default | Extra          |
+-------+------------------+------+-----+---------+----------------+
| id    | int(11) unsigned | NO   | PRI | NULL    | auto_increment |
| name  | varchar(45)      | NO   |     | NULL    |                |
+-------+------------------+------+-----+---------+----------------+
```

And the additional table `users_roles` to map the relationship between these two entities (*user* and *role*):

```
+---------+------------------+------+-----+---------+-------+
| Field   | Type             | Null | Key | Default | Extra |
+---------+------------------+------+-----+---------+-------+
| id_user | int(11) unsigned | NO   | PRI | NULL    |       |
| id_role | int(11) unsigned | NO   | PRI | NULL    |       |
+---------+------------------+------+-----+---------+-------+
```

For the model mapping, we add a attribute in *User_model* as cardinality `has_many` to reference *Role_model* and vice versa:

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

The *doc param* `@table` is necessary in this case, to inform which additional table will store the *many to many* relationship between *user* and *role*.

## In Action

Once mapped your models, we can easily save/fetch objects from database:

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

Returns an array of instances according passed params.

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

*paged result:*
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

Returns an entity instance by your id.

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

Returns an integer of how many rows exists in table according passed params.

* $filters `Array` `optional` default `array()`
* $distinct `Array` `optional` default `array()`

```php
User_model::count() //8
User_model::count(['name' => 'Rafael']) //1
```

### ->fill($properties)

Fill the object with values passed by param. This method receive an array (key => value format) where the *key must match with the name of object attributes*.

* $properties `Array` `required`

```php
$user = new User_model();
$user->fill([
    'name' => 'Leonardo',
    'email' => 'leonardo@turtleninja.com'
]);
```

### ->delete()

Removes the object from database.

```php
User_model::get(1)->delete(); // or
//---------------------------
$user = User_model::get(1);
$user->delete();
```

### ->save()

Saves the object on database.

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
