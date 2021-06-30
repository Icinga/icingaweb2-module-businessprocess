
## VERSION 3  LIGHTNING

 * Version **3.2** - Add SequenceMatcher::opStrToInt()
   * 2020-09-03 21:48  **3.2.6**  fix: empty $old or $new emits "undefined index"
      * cdbccef fix: empty $old or $new emits "undefined index"
   * 2020-08-23 18:26  **3.2.5**  fix a regression about the last output block
      * c2a5b06 fix: regression about the last output block
      * 371d4e2 chore: do not fix styles for files in tests/data/
      * b5c3860 chore: update deps
   * 2020-08-22 13:34  **3.2.4**  fix: "ignoreWhitespace"
      * 46deb24 fix: "ignoreWhitespace" is not working as intended
      * 9a7fd38 feat: add getOptions() method
      * 8200cb3 refactor: tiny tidy codes
      * 8d8fea9 chore(ci): migrate CI from Travis to GitHub Actions
      * 37ff494 chore: update license year
   * 2020-05-28 03:25  **3.2.3**  allow PHP 8
      * 43cfe97 chore: update deps
      * 20a4dff chore: Composer PHP constrain >=7.1.3
      * b707afc docs: use markdown grammar badges in readme
   * 2020-03-06 21:39  **3.2.2**  Fix getGroupedOpcodes(0) behavior
      * 2a97977 Fix getGroupedOpcodes(0) should not have leading/trailing OP_EQ blocks
      * 6d35ebc Improve phpdocs
      * 1fc4d29 Update deps
   * 2020-02-27 16:08  **3.2.1**  Improve const phpdoc
      * 874b98c Improve const phpdoc
      * 1bf57a4 Update deps
   * 2020-01-05 15:34  **3.2.0**  initial release
      * b95a045 Add SequenceMatcher::opStrToInt()
      * bed369c Update deps
      * 33397a8 Update .travis.yml to PHP 7.4 stable
      * 06e9538 $ composer fix
      * 9197cb7 Update deps
      * 7d28307 Fix PSR-12
      * 11e0c04 Add php_codesniffer
      * 9276a3b Update readme to use badges from shields.io
      * bc18f64 Update composer description
      * 4091fb7 Update deps
      * 8e93ecd Add .gitattributes
      * e484eb0 $ composer fix
      * bbd8cd9 Update deps
      * 177f442 Update .travis.yml for 7.4snapshot
      * 1face1c Add .editorconfig
      * a56e19e Update deps
      * c4de6d3 nits
      * 53f82af Remove useless codes

 * Version **3.1** - OP_NOP
   * 2019-02-22 22:44  **3.1.1**  nits
      * dc42d28 nits
   * 2019-02-22 21:28  **3.1.0**  initial release
      * e6546c7 Add SequenceMatcher::OP_NOP

 * Version **3.0** - LIGHTNING
   * 2019-02-22 01:27  **3.0.0**  initial release
      * fc7d2eb Always use int OP
      * 65a2f64 Update deps

## VERSION 2  AFTERNOON

 * Version **2.0** - afternoon
   * 2019-02-21 04:56  **2.0.1**  remove dead codes
      * 09186cb Make SequenceMatcher::linesAreDifferent() private
      * 4a7ca55 Remove unused codes
      * 58f2016 nits
      * fe0974d Fix a typo
   * 2019-02-20 14:45  **2.0.0**  initial release
      * 9336cf3 [BC break] Make constants name shorter
      * 3c86b71 Add ability to generate int opcodes
      * 05263b1 Update deps
      * e493ad1 nits
      * 43a85c7 Add travis CI
      * 9aac92d Add Codacy badge
      * 5e38297 Release of new version 1.0.0

## VERSION 1  INIT RELEASE

 * Version **1.0** - init release
   * 2019-02-08 09:39  **1.0.0**  initial release