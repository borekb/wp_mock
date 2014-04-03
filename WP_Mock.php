<?php
/**
 * WP_Mock
 *
 * LICENSE
 *
 * Copyright 2013 10up and other contributors
 * http://10up.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package    WP_Mock
 * @copyright  Copyright (c) 2013 10up (http://10up.com)
 * @license    MIT License
 */

use WP_Mock\Matcher\FuzzyObject;

class WP_Mock {
	/**
	 * @var \WP_Mock\EventManager
	 */
	protected static $event_manager;

	/**
	 * @var \WP_Mock\Functions
	 */
	protected static $function_manager;

	protected static $__bootstrapped = false;

	protected static $__use_patchwork = false;

	/**
	 * @param boolean $use_patchwork
	 */
	public static function setUsePatchwork( $use_patchwork ) {
		if ( ! self::$__bootstrapped ) {
			self::$__use_patchwork = (bool) $use_patchwork;
		}
	}

	public static function usingPatchwork() {
		return (bool) self::$__use_patchwork;
	}

	/**
	 * Bootstrap WP_Mock
	 */
	public static function bootstrap() {
		if ( ! self::$__bootstrapped ) {
			self::$__bootstrapped = true;
			require_once __DIR__ . '/WP_Mock/API/function-mocks.php';
			require_once __DIR__ . '/WP_Mock/API/constant-mocks.php';
			self::setUp();
		}
	}

	/**
	 * Make sure Mockery doesn't have anything set up already.
	 */
	public static function setUp() {
		if ( self::$__bootstrapped ) {
			\Mockery::close();

			self::$event_manager    = new \WP_Mock\EventManager();
			self::$function_manager = new \WP_Mock\Functions();
		} else {
			self::bootstrap();
		}
	}

	/**
	 * Tear down anything built up inside Mockery when we're ready to do so.
	 */
	public static function tearDown() {
		self::$event_manager->flush();
		self::$function_manager->flush();

		\Mockery::close();
		\WP_Mock\Handler::cleanup();
	}

	/**
	 * Fire a specific (mocked) callback when an apply_filters() call is used.
	 *
	 * @param string $filter
	 *
	 * @return \WP_Mock\Filter
	 */
	public static function onFilter( $filter ) {
		return self::$event_manager->filter( $filter );
	}

	/**
	 * Fire a specific (mocked) callback when a do_action() call is used.
	 *
	 * @param string $action
	 *
	 * @return \WP_Mock\Action
	 */
	public static function onAction( $action ) {
		return self::$event_manager->action( $action );
	}

	/**
	 * Get a filter or action added callback object
	 *
	 * @param string $hook
	 * @param string $type
	 *
	 * @return \WP_Mock\HookedCallback
	 */
	public static function onHookAdded( $hook, $type = 'filter' ) {
		return self::$event_manager->callback( $hook, $type );
	}

	/**
	 * Get a filter added callback object
	 *
	 * @param string $hook
	 *
	 * @return \WP_Mock\HookedCallback
	 */
	public static function onFilterAdded( $hook ) {
		return self::onHookAdded( $hook, 'filter' );
	}

	/**
	 * Get an action added callback object
	 *
	 * @param string $hook
	 *
	 * @return \WP_Mock\HookedCallback
	 */
	public static function onActionAdded( $hook ) {
		return self::onHookAdded( $hook, 'action' );
	}

	/**
	 * Alert the Event Manager that an action has been invoked.
	 *
	 * @param string $action
	 */
	public static function invokeAction( $action ) {
		self::$event_manager->called( $action );
	}

	public static function addFilter( $hook ) {
		self::addHook( $hook, 'filter' );
	}

	public static function addAction( $hook ) {
		self::addHook( $hook, 'action' );
	}

	public static function addHook( $hook, $type = 'filter' ) {
		$type_name = "$type::$hook";
		self::$event_manager->called( $type_name, 'callback' );
	}

	/**
	 * Set up the expectation that an action will be called during the test.
	 *
	 * Mock a WordPress action, regardless of the parameters used.  This call merely
	 * verifies that the action is invoked by the tested method.
	 *
	 * @param string $action Action we expect the method to call
	 */
	public static function expectAction( $action ) {
		$intercept = \Mockery::mock( 'intercept' );
		$intercept->shouldReceive( 'intercepted' )->atLeast()->once();
		$args = func_get_args();
		$args = count( $args ) > 1 ? array_slice( $args, 1 ) : array( null );

		$action    = self::onAction( $action );
		$responder = call_user_func_array( array( $action, 'with' ), $args );
		$responder->perform( array( $intercept, 'intercepted' ) );
	}

	public static function assertActionsCalled() {
		if ( ! self::$event_manager->allActionsCalled() ) {
			$failed = implode( ', ', self::$event_manager->expectedActions() );
			throw new PHPUnit_Framework_ExpectationFailedException( 'Method failed to invoke actions: ' . $failed, null );
		}
	}

	/**
	 * Add an expectation that an action should be added
	 *
	 * Really just a wrapper function for expectHookAdded()
	 *
	 * @param string $action   The action name
	 * @param string $callback The callback that should be registered
	 * @param int    $priority The priority it should be registered at
	 * @param int    $args     The number of arguments that should be allowed
	 */
	public static function expectActionAdded( $action, $callback, $priority = 10, $args = 1 ) {
		self::expectHookAdded( 'action', $action, $callback, $priority, $args );
	}

	/**
	 * Add an expectation that a filter should be added
	 *
	 * Really just a wrapper function for expectHookAdded()
	 *
	 * @param string $filter   The action name
	 * @param string $callback The callback that should be registered
	 * @param int    $priority The priority it should be registered at
	 * @param int    $args     The number of arguments that should be allowed
	 */
	public static function expectFilterAdded( $filter, $callback, $priority = 10, $args = 1 ) {
		self::expectHookAdded( 'filter', $filter, $callback, $priority, $args );
	}

	/**
	 * Add an expectation that a hook should be added
	 *
	 * @param string $type     The type of hook being added
	 * @param string $action   The action name
	 * @param string $callback The callback that should be registered
	 * @param int    $priority The priority it should be registered at
	 * @param int    $args     The number of arguments that should be allowed
	 */
	public static function expectHookAdded( $type, $action, $callback, $priority = 10, $args = 1 ) {
		$intercept = \Mockery::mock( 'intercept' );
		$intercept->shouldReceive( 'intercepted' )->atLeast()->once();

		/** @var WP_Mock\HookedCallbackResponder $responder */
		$responder = self::onHookAdded( $action, $type )
			->with( $callback, $priority, $args );
		$responder->perform( array( $intercept, 'intercepted' ) );
	}

	public static function assertHooksAdded() {
		if ( ! self:: $event_manager->allHooksAdded() ) {
			$failed = implode( ', ', self::$event_manager->expectedHooks() );
			throw new PHPUnit_Framework_ExpectationFailedException( 'Method failed to add hooks: ' . $failed, null );
		}
	}

	/**
	 * Mock a WordPress API function
	 *
	 * This function registers a mock object for a WordPress function and, if
	 * necessary, dynamically defines the function. Pass the function name as
	 * the first argument (e.g. wp_remote_get) and pass in details about the
	 * expectations in the $arguments array. The arguments array has a few
	 * options for defining expectations about how the WordPress function should
	 * be used during a test. Currently, it accepts the following settings:
	 *
	 * - times: Defines expectations for the number of times a function should
	 *   be called. The default is 0 or more times. To expect the function to be
	 *   called an exact amount of times, set times to a non-negative numeric
	 *   value. To specify that the function should be called a minimum number
	 *   of times, use a string with the minimum followed by '+' (e.g. '3+'
	 *   means 3 or more times). Append a '-' to indicate a maximum number of
	 *   times a function should be called (e.g. '3-' means no more than 3 times)
	 *   To indicate a range, use '-' between two numbers (e.g. '2-5' means at
	 *   least 2 times and no more than 5 times)
	 * - return: Defines the value (if any) that the function should return. If
	 *   you pass a Closure as the return value, the function will return
	 *   whatever the Closure's return value.
	 * - return_in_order: Use this if your function will be called multiple
	 *   times in the test but needs to have different return values. Set this to
	 *   an array of return values. Each time the function is called, it will
	 *   return the next value in the sequence until it reaches the last value,
	 *   which will become the return value for all subsequent calls. For
	 *   example, if I am mocking is_single(), I can set return_in_order to
	 *   array( false, true ). The first time is_single() is called it will
	 *   return false. The second and all subsequent times it will return true.
	 *   Setting this value overrides return, so if you set both, return will be
	 *   ignored.
	 * - return_arg: Use this to specify that the function should return one of
	 *   its arguments. return_arg should be the position of the argument in the
	 *   arguments array, so 0 for the first argument, 1 for the second, etc.
	 *   You can also set this to true, which is equivalent to 0. This will
	 *   override both return and return_in_order.
	 * - args: Use this to set expectations about what the arguments passed to
	 *   the function should be. This value should always be an array with the
	 *   arguments in order. Like with return, if you use a Closure, its return
	 *   value will be used to validate the argument expectations. WP_Mock has
	 *   several helper functions to make this feature more flexible. The are
	 *   static methods on the \WP_Mock\Functions class. They are:
	 *   - Functions::type( $type ) Expects an argument of a certain type. This
	 *     can be any core PHP data type (string, int, resource, callable, etc.)
	 *     or any class or interface name.
	 *   - Functions::anyOf( $values ) Expects the argument to be any value in
	 *     the $values array
	 *   In addition to these helper functions, you can indicate that the
	 *   argument can be any value of any type by using '*'. So, for example, if
	 *   I am expecting get_post_meta to be called, the args array might look
	 *   something like this:
	 *     array( $post->ID, 'some_meta_key', true )
	 *
	 * @param string $function_name
	 * @param array  $arguments
	 */
	public static function wpFunction( $function_name, $arguments = array() ) {
		self::$function_manager->register( $function_name, $arguments );
	}

	/**
	 * A wrapper for wpFunction that will simply set/override the return to be
	 * a function that returns the value that its passed. For example, esc_attr
	 * may need to be mocked, and it must return some value. wpPassthruFunction
	 * will set esc_attr to return the value its passed.
	 *
	 *    \WP_Mock::wpPassthruFunction( 'esc_attr' );
	 *    echo esc_attr( 'some_value' ); // echoes "some_value"
	 *
	 * @param string $function_name
	 * @param array  $arguments
	 */
	public static function wpPassthruFunction( $function_name, $arguments = array() ) {
		$arguments           = (array) $arguments;
		$arguments['return'] = function ( $param ) {
			return $param;
		};
		self::$function_manager->register( $function_name, $arguments );
	}

	/**
	 * Generate a fuzzy object match expectation
	 *
	 * This will let you fuzzy match objects based on their properties without
	 * needing to use the identical (===) operator. This is helpful when the
	 * object being passed to a function is constructed inside the scope of the
	 * function being tested but where you want to make assertions on more than
	 * just the type of the object.
	 *
	 * @param $thing
	 *
	 * @return FuzzyObject
	 */
	public static function fuzzyObject( $thing ) {
		return new FuzzyObject( $thing );
	}
}
