require("./scss/style.scss");
require("materialize-css/dist/js/materialize.min.js")

$(document).ready(function(){
  $('.parallax').parallax();
});

window.addEventListener('scroll', function (e) {
  var nav = document.getElementById('main-navigation');
  if (document.documentElement.scrollTop || document.body.scrollTop > window.innerHeight) {
    nav.classList.add('nav-colored');
    nav.classList.remove('nav-transparent');
  } else {
    nav.classList.add('nav-transparent');
    nav.classList.remove('nav-colored');
  }
});
