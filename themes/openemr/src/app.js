require("./scss/style.scss");
require("materialize-css/dist/js/materialize.min.js");
require("bootstrap/dist/js/bootstrap.bundle.js");

function counter(obj_id, start, end) {
  var obj = document.getElementById(obj_id);
  var current = start;
  var timer = setInterval(function(){
    current += 1;
    obj.innerHTML = current;
    if (current == end) {
      clearInterval(timer);
    }
  }, 1);
}

$(document).ready(function(){
  $('.parallax').parallax();
});

window.addEventListener('scroll', function (e) {
  var nav = document.getElementById("main-navigation");
  if (document.documentElement.scrollTop || document.body.scrollTop > window.innerHeight) {
    nav.classList.add('nav-colored');
    nav.classList.remove('nav-transparent');
  } else {
    nav.classList.add('nav-transparent');
    nav.classList.remove('nav-colored');
  }
});
