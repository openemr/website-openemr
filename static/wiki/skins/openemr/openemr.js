// NOTE: There isn't much to the OpenEMR website/wiki at the moment. Abstract out
// this code later if there is non-trivial changes applied/code becomes hard to maintain.
jQuery(document).ready(function() {
  'use strict';

  // Cheap browser check to make sure we are on a browser
  // that is at least IE9, Firefox v45, or Chrome v29.
  // Applies to mobile browsers as well.
  function browserSupportCheck() {
    var oldBrowserRedirect = 'http://www.old-browser.org/en-us/';
    var referringSiteParam = '?referer=www.open-emr.org';

    if (!window.btoa) {
      window.location = oldBrowserRedirect + referringSiteParam;
    }
  }

  function setActivePage() {
    var urlPathName = window.location.pathname;
    var isInWiki = (urlPathName.search(/wiki/) !== -1);
    var pageName = urlPathName.split('/').slice(-1)[0];

    var pageLinkMapping = [{
      name: 'OpenEMR_Features',
      css: '.features-link',
      isADropDownNode: false
    }, {
      name: 'OpenEMR_Demo',
      css: '.demo-link',
      isADropDownNode: false
    }, {
      name: 'OpenEMR_Downloads',
      css: '.download-link',
      isADropDownNode: false
    }, {
      name: 'OpenEMR_Support_Guide',
      css: '.support-link',
      isADropDownNode: true
    }];

    for (var i = 0; i < pageLinkMapping.length; i++) {
      var target = null;

      if (pageName === pageLinkMapping[i].name) {
        if (pageLinkMapping[i].isADropDownNode) {
          // This means the root link is just a toggle for the dropdown and, therefore,
          // it is in it's own nested ul so we must go into that ul in order to style
          // this active root link
          target = 'ul ' + pageLinkMapping[i].css;
        } else {
          target = pageLinkMapping[i].css;
        }
      } else if (isInWiki && i === (pageLinkMapping.length - 1)) {
        target = '.wiki-link';
      }

      if (target) {
        jQuery(target).addClass('active-link');
        break;
      }
    }
  }

  function cycleCarousel() {
    var cycleIntervalInMilliseconds = 8000;

    window.setInterval(function() {
      var slideCount = null;
      var allSlidesClass = '.carousel .slide';
      var hideClass = 'hide';
      var allSlides = jQuery(allSlidesClass);
      var currentSlideIndex = null;
      slideCount = allSlides.length;

      (allSlides || []).each(function() {
        var currentSlide = jQuery(this);
        var nextSlide = null;

        if (currentSlide.hasClass(hideClass)) { return; }

        // This plucks the number out of the indexed class (e.g.: `slide-1` is `1`)
        currentSlideIndex = parseInt(currentSlide.prop('classList')[1].replace('slide-', ''), 10);
        currentSlide.addClass(hideClass);

        if (currentSlideIndex + 1 === slideCount) {
          currentSlideIndex = 0;
        } else {
          currentSlideIndex++;
        }

        nextSlide = jQuery(allSlidesClass + '-' + currentSlideIndex);
        nextSlide.removeClass(hideClass);

       return false;
      });
    }, cycleIntervalInMilliseconds);
  }

  // Constructor that is ran on each page load
  function init() {
    browserSupportCheck();
    cycleCarousel();
    setActivePage();
  }

  init();
});
