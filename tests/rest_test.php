<?php

class RestTest extends VaeUnitTestCase {

  function testVaeArrayFromRailsXml() {
    $xml = simplexml_load_file(dirname(__FILE__) . "/webroot/rails-xml-test.xml");
    $php = array (
      'code' => 'BLOGLINGS',
      'created_at' => '2009-02-27T14:02:30-05:00',
      'description' => 'Blogger Discount Code',
      'discount_shipping' => false,
      'excluded_classes' => '42583,55009',
      'fixed_amount' => '',
      'free_shipping' => false,
      'id' => '186',
      'included_classes' => '',
      'max_per_customer' => '',
      'min_order_amount' => '',
      'min_order_items' => '',
      'number_available' => '',
      'percentage_amount' => '50.0',
      'required_classes' => '',
      'start_at' => '',
      'stop_at' => '',
      'updated_at' => '2009-09-24T19:30:11-04:00',
      'website_id' => '29',
    );
    $this->assertEqual($php, _vae_array_from_rails_xml($xml));
  }

  function testVaeBuildXml() {
    $arr = array('name' => 'Freefall', 'tour_dates' => array(37465 => array('venue' => 'Mercury Lounge')));
    $xml = "<content><name>Freefall</name><tour_dates><item><venue>Mercury Lounge</venue></item></tour_dates></content>";
    $this->assertEqual($xml, _vae_build_xml("content", $arr, "foo"));

    // Test safe params
    $arr = array('name' => "Kevin");
    $xml = "<customer><name>Kevin</name></customer>";
    $this->assertEqual($xml, _vae_build_xml("customer", $arr, "api/site/v1/customers/create_or_update"));
    $arr = array('name' => "Kevin", 'bad_param' => "Foo");
    $this->assertEqual($xml, _vae_build_xml("customer", $arr, "api/site/v1/customers/create_or_update"));
  }

  function testVaeCreate() {
    global $_VAE;
    $this->mockrest("<row><id>123</id></row>");
    $this->assertEqual(123, _vae_create(12345, 0, array('name' => "Freefall2")));
    $this->assertRest();
  }

  function testVaeCreateRestError() {
    global $_VAE;
    $this->mockRestError();
    $this->assertFalse(_vae_create(12345, 0, array('name' => "Freefall2")));
  }

  function testVaeProxy() {
    $this->mockRest("<img src=\"image.jpg\" /><img src=\"http://google.com/image.jpg\" /><img src='/image.jpg' /><img src='http://google.com/image.jpg' />");
    $this->assertEqual("<img src=\"http://btg.vaesite.com/image.jpg\" /><img src=\"http://google.com/image.jpg\" /><img src='http://btg.vaesite.com//image.jpg' /><img src='http://google.com/image.jpg' />", _vae_proxy("fruit", "apple=orange", true));
  }

  function testVaeRest() {
    $this->assertNotEqual(false, _vae_rest(array('name' => "Freefall"), "content/update/13421", "content"));
    $this->assertRest();
  }

  function testVaeRestTagErrors() {
    $tag = $this->callbackTag('<v:update path="/13421"><v:text_field path="name" required="true" /></v:update>');
    $this->assertFalse(_vae_rest(array('name' => "Freefall"), "content/update/13421", "content", $tag));
    $this->assertErrors("Name can't be blank");
    $this->assertNoRest();
  }

  function testVaeRestRestError() {
    $this->mockRestError();
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content"));
    $this->assertRestError();
  }

  function testVaeRestRailsSupressesAllError() {
    global $_VAE;
    $this->mockRestError("This is a real situation.", "404");
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content", null, true));
    $this->assertEqual("404", $_VAE['reststatus']);
  }

  function testVaeRestRails404Error() {
    global $_VAE;
    $this->mockRestError("This is a real situation.", "404");
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content", null, ['404']));
    $this->assertEqual("404", $_VAE['reststatus']);
  }

  function testVaeRestRails504Error() {
    global $_VAE;
    $this->mockRestError("Gateway Timeout", "504");
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content", null, ['504']));
    $this->assertEqual("504", $_VAE['reststatus']);
  }

  function testVaeRestRailsError() {
    $this->mockRestError("This is not a real situation.", "404");
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content"));
    $this->assertErrors("This is not a real situation.");
  }

  function testVaeRestMissingParamsError() {
    $this->assertFalse( _vae_rest(array(), "api/site/v1/store/create_order", "order"));
    $this->assertErrors("No parameters were provided.");
  }

  function testVaeRestRestErrorHidden() {
    $this->mockRestError();
    $this->assertFalse( _vae_rest(array('name' => "Freefall"), "content/update/13421", "content", null, null, true));
    $this->assertNoErrors();
  }

  function testVaeSafeMethodName() {
    $this->assertEqual("api", _vae_safe_method_name("api"));
    $this->assertEqual("api/v1", _vae_safe_method_name("api/v1"));
    $this->assertEqual("api/v1/store", _vae_safe_method_name("api/v1/store"));
    $this->assertEqual("api/v1/store", _vae_safe_method_name("api/v1/store/123"));
    $this->assertEqual("api/v1/foo/update", _vae_safe_method_name("api/v1/foo/123/update"));
  }

  function testVaeSendRest() {
    $this->mockRest("test");
    $errors = array();
    $this->assertEqual("test", _vae_send_rest($url, array(), $errors));
    $this->assertRest();
  }

  function testVaeSendRestError() {
    $this->mockRestError("test");
    $errors = array();
    $this->assertFalse(_vae_send_rest($url, array(), $errors));
  }

  function testVaeSimpleRest() {
    $this->mockRest("test");
    $this->assertEqual("test", _vae_simple_rest($url));
    $this->assertRest();
  }

  function testVaeUpdate() {
    global $_VAE;
    $this->assertNotEqual(false, _vae_update(13421, array('name' => "Freefall2")));
    $this->assertRest();
    $this->assertEqual($_VAE['run_hooks'], array(array("content:updated", 13421)));
  }

  function testVaeUpdateRestError() {
    global $_VAE;
    $this->mockRestError();
    $this->assertFalse(_vae_update(13421, array('name' => "Freefall2")));
    $this->assertNull($_VAE['run_hooks']);
  }

}

?>
