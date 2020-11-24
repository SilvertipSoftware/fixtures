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

<?php
    foreach ([3, 4, 5] as $num) {
?>
fixture<?= $num ?>:
  name: Person <?= $num ?> 
  age: <?= 10*$num ?> 
<?php
    }
?>