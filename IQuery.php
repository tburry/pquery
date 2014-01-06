<?php

namespace pQuery;

interface IQuery {
   /// Methods ///

   /**
    * Adds the specified class(es) to each of the set of matched elements.
    */
   function addClass($classname);

   /**
    * Insert content, specified by the parameter, after each element in the set of matched elements.
    */
   function after($content);

   /**
    * Insert content, specified by the parameter, to the end of each element in the set of matched elements.
    */
   function append($content);

   /**
    * Get the value of an attribute for the first element in the set of matched elements or set one
    * or more attributes for every matched element.
    */
   function attr($name, $value = null);

   /**
    * Insert content, specified by the parameter, before each element in the set of matched elements.
    */
   function before($content);

   /**
    * Remove all child nodes of the set of matched elements from the DOM.
    */
   function clear();

   /**
    * Gets the count of matched elements.
    */
   function count();

   /**
    * Get the value of a style property for the first element in the set of matched elements or
    * set one or more CSS properties for every matched element.
    */
//   function css($name, $value = null);

   /**
    * Determine whether any of the matched elements are assigned the given class.
    */
   function hasClass($classname);

   /**
    * Get the HTML contents of the first element in the set of matched elements
    * or set the HTML contents of every matched element.
    */
   function html($value = null);

   /**
    * Insert content, specified by the parameter, to the beginning of each element in the set of matched elements.
    */
   function prepend($content = null);

   /**
    * Get the value of a property for the first element in the set of matched elements
    * or set one or more properties for every matched element.
    */
   function prop($name, $value = null);

   /**
    * Remove the set of matched elements from the DOM.
    */
   function remove($selector = null);

   /**
    * Remove an attribute from each element in the set of matched elements.
    */
   function removeAttr($name);

   /**
    * Remove a single class, multiple classes, or all classes from each element in the set of matched elements.
    */
   function removeClass($classname);

   /**
    * Remove a property for the set of matched elements.
    */
   function removeProp($name);

   /**
    * Returns the name of the element.
    */
   function tagName($value = null);

   /**
    * Get the combined text contents of each element in the set of matched elements, including their descendants, or set the text contents of the matched elements.
    */
   function text($value = null);

   /**
    * Add or remove one or more classes from each element in the set of matched elements,
    * depending on either the class’s presence or the value of the switch argument.
    */
   function toggleClass($classname, $switch = null);

   /**
    * Remove the parents of the set of matched elements from the DOM, leaving the matched elements in their place.
    */
   function unwrap();

   /**
    * Get the current value of the first element in the set of matched elements or set the value of every matched element.
    */
   function val($value = null);

   /**
    * Wrap an HTML structure around each element in the set of matched elements.
    */
   function wrap($wrapping_element);

   /**
    * Wrap an HTML structure around the content of each element in the set of matched elements.
    */
   function wrapInner($wrapping_element);
}

