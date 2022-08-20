---
title: "GSoC 2020 Work Product: Modernize OpenEMR’s User Interface"
author:
  name: Guan-Wu Su
  twitter: none
date: 2020-08-25T11:00:00-08:00
slug: gsoc-2020-work-product-modernize-openemr-user-interface
images:
  - images/laptop-person.jpg
coverImage: images/laptop-person.jpg
coverImageStyle: full
---
#### Guan-Wu Su | [Email](mailto:guanwu01509@gmail.com) | [GitHub](https://github.com/stu01509) | [LinkedIn](https://www.linkedin.com/in/cliffsu/) | [ Project](https://github.com/openemr/openemr)

<p align="center">
  <img src="https://i.imgur.com/8CvtSol.png">
</p>

I’m Guan-Wu Su, a junior computer science student from Taiwan. Before I tell you what I did for my project, I would like to thank the OpenEMR community and mentors for the opportunity to work and learn with them.

I am especially thankful to my mentors [Brady](https://github.com/bradymiller) and [Tyler](https://github.com/tywrenn) who passed on their in-depth knowledge of the codebase, reviewed my pull requests, and provided feedback. Also, I would like to give special thanks to [Stephen](https://github.com/stephenwaite) and [Jerry](https://github.com/sjpadgett), who helped me whenever I had questions. This has been a wonderful experience, which I will cherish.


## Introduction
My project aimed to improve the user interface, upgrade the codebase, remove outdated syntax, and remove legacy libraries, such as jQuery-UI. This will future-proof the codebase making it more usable, accessible, and maintainable into the future.

The main project ideas are listed below:

- Improve the Bootstrap user interface, optimize responsive design, and standardize styling via a styling guide.
- Cleanup redundant code.
- Remove outdated syntax and styles.
- Remove legacy javascript components, such as JQuery-UI.
- Import ESLint to normalize the javascript coding style.

## Summary
I began work on OpenEMR on the 23th of February with some issue fixes, Since then, at the time of this report, I have had 96 [commits](https://github.com/openemr/openemr/commits?author=stu01509) brought into the OpenEMR codebase which involved improvements in 452 files and I have 7 pull requests still undergoing code review. A listing of my PR’s can be found below. The majority of the work outlined in the above Introduction has been completed.

The jQuery UI removal has been completed except for one place in the
 **Encounter > Eye Exam** that relies on the old jQuery UI draggable framework.

The user interface refinement and redesign has been mostly completed except for the **Reports**, **Miscellaneous**, **Popups**, and **About** sections and several pages have missing Bootstrap tooltips.

The ESlint project is still a work in progress at the time of this writing. I estimate that I should finish it before the end of GSoC. Each javascript file will be converted to follow the ESlint rules. The javascript that is integrated in PHP files will require another ESlint package for validation.

## Contributions
### Merged PRs
| Name | Issue No. | Topic |
| -------- | -------- | -------- |
| [Issue Fix #2955: Improve README.md ](https://github.com/openemr/openemr/pull/2962) | [#2955](https://github.com/openemr/openemr/issues/2955) | |
| [Issue Fix #2947: Add dark and superhero themes](https://github.com/openemr/openemr/pull/2963) | [#2947](https://github.com/openemr/openemr/issues/2947) |  |
| [Feat: update all theme screenshot](https://github.com/openemr/openemr/pull/2965) | | |
| [Issue Fix #2958: Improve Logs Viewer UI](https://github.com/openemr/openemr/pull/2967) | [#2958](https://github.com/openemr/openemr/issues/2958) | UI Modernization |
| [Issue Fix #2959: Improve language editor UI](https://github.com/openemr/openemr/pull/2978) | [#2959](https://github.com/openemr/openemr/issues/2959) | UI Modernization |
| [Issue Fix #2989: Form label field alignment issue](https://github.com/openemr/openemr/pull/2993) | [#2989](https://github.com/openemr/openemr/issues/2989) | UI Modernization |
| [Fix #3004 change jquery ui tooltip](https://github.com/openemr/openemr/pull/3022) | [#3004](https://github.com/openemr/openemr/issues/3004) | jQuery-UI Removal |
| [Fix #3029 close p tag](https://github.com/openemr/openemr/pull/3030) | [#3029](https://github.com/openemr/openemr/issues/3029) | |
| [Fix #3002 change encounter tabs to bootstrap style](https://github.com/openemr/openemr/pull/3039) | [#3002](https://github.com/openemr/openemr/issues/3002) | UI Modernization |
| [Fix #3040 empty note content](https://github.com/openemr/openemr/pull/3046) | [#3040](https://github.com/openemr/openemr/issues/3040) | |
| [Fix #3047 remove outdated syntax](https://github.com/openemr/openemr/pull/3051) | [#3047](https://github.com/openemr/openemr/issues/3047) | |
| [Fix #3061 facilities modal](https://github.com/openemr/openemr/pull/3064) | [#3061](https://github.com/openemr/openemr/issues/3061) | UI Modernization |
| [Fix #3060 Add expand and go to top button cursor](https://github.com/openemr/openemr/pull/3067) | [#3060](https://github.com/openemr/openemr/issues/3060) | |
| [Fix #3062 calendar text link](https://github.com/openemr/openemr/pull/3069) | [#3062](https://github.com/openemr/openemr/issues/3062) | |
| [Fix #3070 refine test table](https://github.com/openemr/openemr/pull/3080) | [#3070](https://github.com/openemr/openemr/issues/3070) | UI Modernization |
| [Fix #3081 categories button and confirm form](https://github.com/openemr/openemr/pull/3089) | [#3081](https://github.com/openemr/openemr/issues/3081) | UI Modernization |
| [Fix #3077 remove jqueryui](https://github.com/openemr/openemr/pull/3116) | [#3077](https://github.com/openemr/openemr/issues/3077) | jQuery-UI Removal |
| [Fix #3104 refine install code set](https://github.com/openemr/openemr/pull/3121) | [#3104](https://github.com/openemr/openemr/issues/3104) | UI Modernization |
| [Fix #3130: load compendium title](https://github.com/openemr/openemr/pull/3134) | [#3130](https://github.com/openemr/openemr/pull/3134) | UI Modernization |
| [Fix #3099: add bootstrap table style and remove outdated syntax](https://github.com/openemr/openemr/pull/3128) | [#3099](https://github.com/openemr/openemr/issues/3099) | UI Modernization |
| [Fix #3164: eob posting - search expand button lost cursor](https://github.com/openemr/openemr/pull/3170) | [#3164](https://github.com/openemr/openemr/issues/3164) | |
| [Fix #3174 patient encounter form field alignment](https://github.com/openemr/openemr/pull/3175) | [#3174](https://github.com/openemr/openemr/issues/3174) | UI Modernization |
| [Fix #3179 Update font awesome](https://github.com/openemr/openemr/pull/3186) | [#3179](https://github.com/openemr/openemr/issues/3179) | |
| [Fix #2949: add diagnostics to navbar](https://github.com/openemr/openemr/pull/3202) | [#2949](https://github.com/openemr/openemr/issues/2949) | UI Modernization |
| [fix: update font awesome refresh to sync icon](https://github.com/openemr/openemr/pull/3214) | [#3213](https://github.com/openemr/openemr/issues/3213) | UI Modernization |
| [feat #3205 : improve diagnostics gui](https://github.com/openemr/openemr/pull/3215) | [#3205](https://github.com/openemr/openemr/issues/3205) | UI Modernization |
| [fix: modal dialog close icon broken.](https://github.com/openemr/openemr/pull/3217) | | |
| [Fix #3166 eob invoice bs4 updates](https://github.com/openemr/openemr/pull/3223) | [#3166](https://github.com/openemr/openemr/issues/3166) | UI Modernization |
| [Fix: register email input lost form-control style](https://github.com/openemr/openemr/pull/3224) |  | UI Modernization |
| [fix: search-plus and search-minus icons broken](https://github.com/openemr/openemr/pull/3225) |  | UI Modernization |
| [Fix #3107 mult language tool style](https://github.com/openemr/openemr/pull/3243) | [#3107](https://github.com/openemr/openemr/issues/3107) | UI Modernization |
| [Fix #3248 appointment search bs4](https://github.com/openemr/openemr/pull/3250) | [#3248](https://github.com/openemr/openemr/issues/3248) | UI Modernization |
| [Fix: css style background url](https://github.com/openemr/openemr/pull/3258) | | |
| [fix: add miss btn-group style](https://github.com/openemr/openemr/pull/3271) | | |
| [fix: change to button tag and add FontAwesome icon](https://github.com/openemr/openemr/pull/3296) | | UI Modernization |
| [Fix #3279 warning closing form](https://github.com/openemr/openemr/pull/3303) | [#3279](https://github.com/openemr/openemr/issues/3279) | |
| [Fix #3003 clickmap remove jquery ui](https://github.com/openemr/openemr/pull/3316) | [#3003](https://github.com/openemr/openemr/issues/3003) | jQuery-UI Removal |
| [fix #3309: duplicate summary tabs and background color](https://github.com/openemr/openemr/pull/3323) | [#3309](https://github.com/openemr/openemr/issues/3309) | |
| [Fix: remove report.css a tag style.](https://github.com/openemr/openemr/pull/3328) |  | |
| [feat #2888: add float calendar widget ](https://github.com/openemr/openemr/pull/3336) | [#2888](https://github.com/openemr/openemr/issues/2888) | UI Modernization |
| [Fix #3341: Add the Bootstrap style in available appointments](https://github.com/openemr/openemr/pull/3371) | [#3341](https://github.com/openemr/openemr/pull/3371) | UI Modernization |
| [Fix #3380: Register modal button](https://github.com/openemr/openemr/pull/3381) | [#3380](https://github.com/openemr/openemr/issues/3380) | UI Modernization |
| [Fix #3387 update jquery](https://github.com/openemr/openemr/pull/3389) | [#3387](https://github.com/openemr/openemr/pull/3389) | |
| [fix #2892: form inner scrollbar](https://github.com/openemr/openemr/pull/3400) | [#2892](https://github.com/openemr/openemr/issues/2892) | |
| [Fix #3383 encounter summary dropdown](https://github.com/openemr/openemr/pull/3404) | [#3383](https://github.com/openemr/openemr/issues/3383) | UI Modernization |
| [Fix #3005 : Remove EDI History jquery ui](https://github.com/openemr/openemr/pull/3416) | [#3005](https://github.com/openemr/openemr/issues/3005) | UI Modernization / jQuery-UI Removal|
| [Feat #3414: add highlight config](https://github.com/openemr/openemr/pull/3457) | [#3414](https://github.com/openemr/openemr/pull/3457) | UI Modernization |
| [Fix #3458: change button layout](https://github.com/openemr/openemr/pull/3465) | [#3458](https://github.com/openemr/openemr/issues/3458) | UI Modernization |
| [docs: update github issue template](https://github.com/openemr/openemr/pull/3491) |  | |
| [Refactor #3527: remove jquery-ui and change to bootstrap components ui](https://github.com/openemr/openemr/pull/3569) | [#3527](https://github.com/openemr/openemr/issues/3527) | UI Modernization / jQuery-UI Removal |
| [Fix #3542: remove outdated type attribute](https://github.com/openemr/openemr/pull/3572) | [#3542](https://github.com/openemr/openemr/issues/3542) | |
| [Fix #3007: remove jquery-ui and outdated code part1](https://github.com/openemr/openemr/pull/3586) | [#3007](https://github.com/openemr/openemr/issues/3007) | jQuery-UI Removal |
| [feat: add the bootstrap style and remove outdated code](https://github.com/openemr/openemr/pull/3589) | | UI Modernization |
| [Feat #3595: Multi site administration and database](https://github.com/openemr/openemr/pull/3596) | [#3595](https://github.com/openemr/openemr/issues/3595) | UI Modernization |
| [Refactor #3542: remove outdated javascript attribute](https://github.com/openemr/openemr/pull/3599) | [#3542](https://github.com/openemr/openemr/issues/3542) | |
| [Refactor: remove outdated font tag](https://github.com/openemr/openemr/pull/3602) | | |
| [Fix #3542: html doctype attribute](https://github.com/openemr/openemr/pull/3610) | [#3542](https://github.com/openemr/openemr/issues/3542) | |
| [Refactor #3603: refine ippf_upgrade page](https://github.com/openemr/openemr/pull/3630) | [#3603](https://github.com/openemr/openemr/issues/3603) | UI Modernization |
| [Refactor: refine the flow board page](https://github.com/openemr/openemr/pull/3650) |  | UI Modernization |
| [Refactor: Refine the recall board and new recall pages](https://github.com/openemr/openemr/pull/3674) |  | UI Modernization |
| [Refactor: refine the message section pages](https://github.com/openemr/openemr/pull/3679) |  | UI Modernization |
| [Refactor: refine the patient finder page](https://github.com/openemr/openemr/pull/3682) |  | UI Modernization |
| [Refactor #3700: refine dashboard disclosures page](https://github.com/openemr/openemr/pull/3701) | [#3700](https://github.com/openemr/openemr/issues/3700) | UI Modernization |
| [Fix: remove invalid code syntax.](https://github.com/openemr/openemr/pull/3703) |  | |
| [fix: firefox vertical align center](https://github.com/openemr/openemr/pull/3711) |  | |
| [Refactor: refine the patient messages and add patient messages pages.](https://github.com/openemr/openemr/pull/3717) |  | UI Modernization |
| [Refactor: remove oudated javascript attribute](https://github.com/openemr/openemr/pull/3718) | [#3542](https://github.com/openemr/openemr/issues/3542) | |
| [Feat: adjust buttons icon](https://github.com/openemr/openemr/pull/3725) |  | UI Modernization |
| [Refactor: refine the prescription list and prescription add pages](https://github.com/openemr/openemr/pull/3726) |  | UI Modernization |
| [Fix medex navbar overflow](https://github.com/openemr/openemr/pull/3732) |  | UI Modernization |
| [Fix #3734: add override css to fix multiple arrow issue #3752](https://github.com/openemr/openemr/pull/3752) | [#3734](https://github.com/openemr/openemr/issues/3734) | |
| [Refactor: refine the amendments add and edit page.](https://github.com/openemr/openemr/pull/3753) | | UI Modernization |
| [Refactor #3737: refine dashboard immunizations page](https://github.com/openemr/openemr/pull/3759) | | UI Modernization |
| [Refactor: remove outdated code and adjusted grid system.](https://github.com/openemr/openemr/pull/3760) | | |
| [feat: refine the setup page.](https://github.com/openemr/openemr/pull/3771) | | UI Modernization |
| [Fix #3778: change the add field max length to 63](https://github.com/openemr/openemr/pull/3783) | [#3778](https://github.com/openemr/openemr/issues/3778) | |
| [Refactor #3779: remove outdated code, and add the bootstrap form style.](https://github.com/openemr/openemr/pull/3787) | [#3779](https://github.com/openemr/openemr/issues/3779) | UI Modernization |
| [Feat: add the bootstrap input style in dashboard documents.](https://github.com/openemr/openemr/pull/3798) |  | UI Modernization |
| [feat #3804: change visit history icon.](https://github.com/openemr/openemr/pull/3805) | [#3804](https://github.com/openemr/openemr/pull/3805) | UI Modernization |
| [Refactor #3817: refine past encounters and documents.](https://github.com/openemr/openemr/pull/3822) | [#3817](https://github.com/openemr/openemr/issues/3817) | UI Modernization |
| [refactor: refine the patient records request.](https://github.com/openemr/openemr/pull/3823) | | UI Modernization |
| [Refactor: refine the encounter delete form page and modal.](https://github.com/openemr/openemr/pull/3826) | | UI Modernization |
| [fix: adjust the flow board appt status button style.](https://github.com/openemr/openemr/pull/3844) |  | UI Modernization |
| [Fix #3842: fix the recall board disappear when click the arrow button. ](https://github.com/openemr/openemr/pull/3851) | [#3842](https://github.com/openemr/openemr/issues/3842) | |
|[refactor: refine the procedure order on summary page.](https://github.com/openemr/openemr/pull/3856#event-3678087351) | | UI Modernization |
|[Refactor #3267: refine the lab page](https://github.com/openemr/openemr/pull/3764) | [#3267](https://github.com/openemr/openemr/issues/3267) | UI Modernization |
<br/>

### On going PRs
| Name | Issue No. | Topic |
| -------- | -------- | -------- |
| [chore: add eslint and eslint-config-airbnb](https://github.com/openemr/openemr/pull/3795) | | ESLint |
| [Refactor: refine the dashboard pages.](https://github.com/openemr/openemr/pull/3803) | | UI Modernization |
| [Refactor: refine all of the patient visit forms.](https://github.com/openemr/openemr/pull/3825) | | UI Modernization |
| [Refactor: refine the fee sheet, new encounter form, procedure order pages.](https://github.com/openemr/openemr/pull/3845) | | UI Modernization |
| [refactor: refine the fee section pages.](https://github.com/openemr/openemr/pull/3857) | | UI Modernization |
| [Refactor: remove unnecessary oe classes.](https://github.com/openemr/openemr/pull/3873) | | |
| [refactor: Refine procedure section](https://github.com/openemr/openemr/pull/3876) | | UI Modernization |
<br/>

### All of my PRs
https://github.com/openemr/openemr/pulls/stu01509
<br/><br/>
