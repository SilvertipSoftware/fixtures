_fixture:
  model_class: CLASSNAME

DEFAULTS: &DEFAULTS
  city: Vancouver

fixture1:
  <<: *DEFAULTS
  name: Fixture 1
  age: <?= 1+1 ?> 
  nickname: "Ace"

fixture2:
  name: <?= __FILE__ ?> 
  age: <?= rand(1,100) ?> 
