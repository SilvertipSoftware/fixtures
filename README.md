## Fixtures

A relatively faithful clone of [ActiveRecord's fixture capability](https://guides.rubyonrails.org/testing.html#the-low-down-on-fixtures).
Much of the docs from there are relevant, but some of the key points are below.

Fixtures are an easy way of setting up a test database to a known point, rather than continuously creating objects via factories in every test.
The are **not** meant to create everything you'd ever need in a test, just the basics to form a reasonable starting point.

Fixtures are written in YAML, and there's one file per Eloquent model class.

### Requirements & Caveats

- Tests must use `DatabaseTransactions`, since the fixtures are created before the tests run, and need to be returned
to the known-good state after each test. This may change in future releases.
- Namespaced Eloquent models are not handled particularly well, although the `model_class` directive goes a long way.
- YAML files are not pre-processed, so no PHP or Blade templating is possible. This will definitely change soon, as it makes user password creation
a chore.
- All fixtures that are defined will **wipe their model's table. DO NOT run tests against data you care about**.

### Basic Usage

Fixtures by default live in the `tests/fixtures` directory, although that is configurable via the `$fixturePath`. Fixture file names and model names 
typically match, so to create a fixture for a `\Framework` model, create `test/fixtures/frameworks.yml` like:

```yaml
# Some frameworks
# Primary keys are automatically assigned by Fixtures
laravel:
    name: Laravel
    language: PHP

# A primary key can also be manually specified, but why would you?
ror:
    id: 5
    name: Ruby on Rails
    language: Ruby

django:
    name: Django
    language: Python
```

When your tests run, these 3 database records will be created, and ids automatically assigned when needed. Accessor macros are also created, so referencing a 
fixture in a test is as easy as:

```php
public function testAccessing() {
    $laravel = $this->frameworks('laravel'); // this is a \Framework instance
    $this->assertEquals('PHP', $laravel->language);
}
```

### Relations

Relations that are instances of `BelongsTo` and `BelongsToMany`, which includes the morphing `MorphTo` and `MorphToMany`, can also be easily set up by
name. Polymorphic relations must include the morph type (the classname by default, or whatever is in your `morphMap`) in parentheses. Fixtures sets 
the `id` (and `type` for polymorphisms) of the relation automatically, so you don't have to juggle manual ids.


```yaml
# orms.yml
# An \Orm belongs to a \Framework
eloquent:
    name: Eloquent
    framework: laravel

ar:
    name: ActiveRecord
    framework: ror

qs:
    name: QuerySets
    framework: django
```

```yaml
# coders.yml
# A \Coder can specialize in either a \Framework or a \ORM (via a polymorphic relationship)
# \Coders also have skills with many \Frameworks (via a many to many relationship)
# Many-to-many labels must be separated by commas.
jane:
    name: Jane Coder
    specialty: ar (\Orm)
    skills: ror, laravel

sue:
    name: Susan Programmer
    specialty: laravel (\Framework)
    skills: laravel, django
```

### Class Names

As mentioned above, namespaced models are not handled well yet, so generally you'll have to tell Fixtures what model class the fixture set is for. 
In your YAML include a record like:

```yaml
_fixture:
    model_class: \App\Models\Framework
```

### Label Interpolation & Defaults

Including the string `$LABEL` in a column definition will replace that tag with the label of the fixture, which is often great for usernames, email
addresses, etc:

```yaml
_fixture:
    model_class: \App\User

mary:
    username: $LABEL
    email: $LABEL@domain.com

barb:
    username: $LABEL
    email: $LABEL@domain.com
```

For repetitive records like the above, a special `DEFAULTS` row can be specified using YAML anchors, so a longer user fixture set or a model with many
fields to set could look like:

```yaml
_fixture:
    model_class: \App\User

DEFAULTS: &DEFAULTS
    username: $LABEL
    email: $LABEL@domain.com

mary:
    <<: *DEFAULTS

barb:
    <<: *DEFAULTS
...
```

Label interpolation is done after default replacement, so this will generate users with the usernames `mary`, `barb`, etc.
