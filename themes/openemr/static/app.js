/**!
 * OpenEMR Template
 */

$(document).ready(function(){
  $('.parallax').parallax();
  var nav = document.getElementById('main-navigation');
  if (nav.classList.contains('home') == true) {
    nav.classList.remove('default-state');
  }
});

window.addEventListener('scroll', function (e) {
  var nav = document.getElementById("main-navigation");
  if (nav.classList.contains("home") == true) {
    if (document.documentElement.scrollTop || document.body.scrollTop > window.innerHeight) {
      nav.classList.add('default-state');
      nav.classList.remove('armed-state');
    } else {
      nav.classList.add('armed-state');
      nav.classList.remove('default-state');
    }
  }
});
