# blot-cover
Plugin to integrate Bolt Cover in to WooCommerce
1/ This code can be added in Function.php file. 
2/ The code sends a POST request to BOLT with details like product name, product id, insurance type, insurance term*, product category**, product price, quantity, insurance price***. 
3/ The code adds a checkbox on the product page that user can select. The selected data is stored in CART PAGE, CHECKOUT PAGE and ORDER AREA. 
*insurance term is set to 5 by default
** product category is hard coded. 
*** Insrance price is set as 0 since insurance is inclusive. 
4/ Make sure you assign categories to each product. 