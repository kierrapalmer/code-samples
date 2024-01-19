import React, { useContext, useEffect, useState } from 'react';
import { StyleSheet, Modal, RefreshControl , Image } from 'react-native';
import { View,ScrollView,  Spinner, Text, } from "native-base";
import mainContext from '@src/Context';
import AsyncStorage from '@react-native-async-storage/async-storage';

import {CloseButton} from '@components/GlobalComponents';
import {MemorizedProductDetail} from '@components/Store/ProductDetail';
import {MemorizedOrderShippingForm} from '@components/Store/OrderShippingForm';
import {MemorizedOrderReview} from '@components/Store/OrderReview';

import {Woocommerce} from '@src/WoocommerceApi';

import TopNavBar from '@components/TopNavBar';
import { global, colors } from '@styles/GlobalStyles.js';
import { secrets, wordpressURL } from '@src/Const';

import base64 from 'react-native-base64'

const spacing = 5;
const iconPath = '../../../assets/images/icons';


export const SwagDetailScreen = ({navigation, route})=>{    
    const { userProfile } = useContext(mainContext);

    const [isProductLoading, setIsProductLoading] = useState(true);
    const [product, setProduct] = useState(null);    
    const [modalVisible, setModalVisible] = useState(false);
    // const [step, setStep] = useState('shipping');
    const [step, setStep] = useState('shipping');


    const [checkConfirm, setCheckConfirm] = useState(false);
    const [customer, setCustomer] = useState({});
    const [order, setOrder] = useState({});
    
    const [refreshing, setRefreshing] = useState(false);
    const onRefresh = React.useCallback(() => {
        setRefreshing(true);
        setTimeout(()=>{setRefreshing(false)}, 1500)
    }, []);
    
    useEffect(() => {
        return () => {
            setProduct(null);
            setCustomer({});
            setOrder({});
        };
    }, []);
    
    useEffect(() => {
        // console.log('prof', userProfile);
        setIsProductLoading(true);
        if(!userProfile?.isGuestLogin){
            getUser();
        }

        if(route.params != null && route.params.productId != null){
            getProduct(route.params.productId);     
        }    else{
            setIsProductLoading(false);    
        }    
        
        
        // navigation.setParams({ screen: undefined, params: undefined });
        
    }, [route.params]);
    
    
    const onChange = (value, name) => {
        if(value != null && name != null){
            setOrder(prevState => ({
                ...prevState,  // shallow copy all previous state
                [name]: value, // update specific key/value
            }));
        }
        
    };
    
    const getUser = async () => { 
        Woocommerce.get('customers/'+userProfile.id)
            .then((response) => {
                if(response.data != null){
                    setCustomer(response.data);
                    // console.log('Customer', response.data);
                } else{
                    console.log('no user found');
                }
            })
            .catch((error) => {
                console.log('Error Retrieving User ', error);
            });
        
    }
    
    const getProduct = async (productId) => {      
        
            
        Woocommerce.get('products/'+productId)
            .then((response) => {
                // console.log('Response Data', response);
                if(response.data != null){
                    // console.log('product', response.data);
                    setProduct(response.data);
                    setIsProductLoading(false);

                } else{
                    console.log('no product found');
                }
            })
            .catch((error) => {
                setIsProductLoading(false);    
                console.log('Error Retrieving Product ', error.response);
            })
    }


    
    const submitOrder = async () => {
        setStep('processing');
        console.log('Start Submit Order', order);
        
        const customerInfo = {
            billing: {
                first_name: order.shipping.firstName,
                last_name: order.shipping.lastName,
                address_1: order.shipping.street1|| '',
                address_2: order.shipping.street2 || '',
                city: order.shipping.city || '',
                state: order.shipping.state || '',
                postcode: order.shipping.zip || '',
                country: "US",
                email: userProfile.email,
                phone: order.shipping.phone
            },
            shipping: {
                first_name: order.shipping.firstName,
                last_name: order.shipping.lastName,
                address_1: order.shipping.street1 || '',
                address_2: order.shipping.street2 || '',
                city: order.shipping.city || '',
                state: order.shipping.state || '',
                postcode: order.shipping.zip || '',
                country: "US",
            },            
        }
        
        const orderInfo = {
            payment_method: "cod",
            payment_method_title: "Cash on Delivery",
            set_paid: true,
            status: 'processing',
            customer_id: customer.id,            
            line_items: [
                {
                product_id: product.id,
                quantity: order.properties.quantity || 1,
                //add meta data here
                },
            ],
            shipping_lines: [
                {
                  method_id: "free_shipping",
                  method_title: "Free Shipping",
                  total: "0.00"
                }
            ]
        };
        
        if(order.properties.size != null){
            orderInfo.line_items[0].meta_data = [
                {
                    "key": "pa_size",
                    "value": order.properties.size.toLowerCase(),
                },
            ];
        }       
        
        
        let data = {
            ...customerInfo,
            ...orderInfo
        };

        
        
        /*--------------------
        Update Customer Shipping/Billing address
        -----------------------*/       
        if(customer != null){
            Woocommerce.put('customers/'+customer.id, customerInfo)
                .then((response) => {
                    console.log('Customer Updated');        
                })
                .catch((error) => {
                    setStep('error');
                    console.log('Error Saving Customer Info ', error.response.data);
                })
        }
        
        
        /*--------------------
        Save User Points
        -----------------------*/
        let currentPoints = parseInt(userProfile.available_points) || 0;
        let orderTotal = parseInt(product.regular_price) * parseInt(order.properties.quantity);
        let newPoints = currentPoints - orderTotal;

        if(newPoints < 0){
            console.log('not enough points- current', currentPoints, 'order total', orderTotal );
            setStep('overdraft');
            return 0;
        }        
        
        let userResponse = await setPoints(newPoints);  
        
        
        /*--------------------
        Submit Woocommerce Order
        -----------------------*/
        if(userResponse.status == '201' || userResponse.status == '200'){
            Woocommerce.post('orders', data)
                .then((response) => {
                    console.log('Order Submitted');
                    console.log('Order Response Data', response);
                    setStep('complete');
        
                })
                .catch((error) => {
                    // setIsProductLoading(false);    
                    setStep('error');
                    console.log('Error Submittin Order ', error.response.data);                    
        
                    //readd points to user on error
                    setPoints(newPoints + orderTotal);
                })
        }
        
        
    }
    
    const setPoints = async (points) => {
        let userResponse = await fetch( wordpressURL + '/wp-json/wp/v2/users/' + userProfile.id, 
            {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Basic ' + base64.encode('mobile_app:'+secrets.wordpressAppPass),
                },
                method: 'POST',
                body: JSON.stringify({
                    'acf': { 'user_fields': { 'available_points': points.toString(),}, },
                }),
            }
        );
        console.log('Set New Points', points);
        
        try {
            userProfile.available_points = points;
            await AsyncStorage.setItem(
              'userProfile',
              JSON.stringify(userProfile)
            );
        } catch (exception){
            console.log('could not save points on current signed in user', exception);
        }
        return userResponse;
    }
    

    const formatPoints = (pts) => {
        return pts ? pts.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") : '';
    };
    
    
    
    const renderModal = () => {
        let itemPoints =  parseInt(product.regular_price);
        let userPoints = parseInt(userProfile.available_points);
        
        
        return (
            <Modal
                animationType="slide"
                visible={modalVisible}
                presentationStyle="pageSheet"
                statusBarTranslucent
                onRequestClose={() => {
                    setModalVisible(!modalVisible);
                }}
                style={[global.btnBlue,]}
                
            >
                <View style={global.modalContainer} flex="1" bg={colors.blueGray} >
                    
                    <ScrollView keyboardShouldPersistTaps="handled">
                        <View style={global.modalInner}  my="5" mx="5"> 
                            { ( (order.properties && userPoints < itemPoints*order.properties.quantity) || step == 'overdraft') ?  
                                <View  mt={100} alignItems="center">
                                    <Image source={require(iconPath+'/x.png')} alt="not enough" 
                                        resizeMode="contain"
                                        style={{width: 250, height: 250, marginBottom: 30,}}
                                    />  
                                              
                                    <Text style={[global.headerL, global.headerFont]}>Not Enough Points</Text>  
                                </View>
                            
                            :
                                <View>                                   
                                    {step == 'shipping' && 
                                        <MemorizedOrderShippingForm navigation={navigation} order={order} customer={customer} setModalVisibleHandler={setModalVisible} shippingNextHandler={ (value) => { 
                                            setOrder((prevState) => ({...prevState, 'shipping':value}));
                                            setStep('review'); 
                                        } } />
                                    }
                                    
                                    {step == 'review' && 
                                        <MemorizedOrderReview product={product} order={order} 
                                            updateStepHandler={val => setStep(val)} submitOrderHandler={submitOrder} />
                                    }
                                    
                                    {step == 'processing' && 
                                        <View  mt={100} alignItems="center">                                            
                                            <Text style={[global.headerL, global.headerFont]}>Processing</Text>  
                                        </View>
                                    }
                                    {step == 'complete' && 
                                        <View  mt={100} alignItems="center"  px="5">
                                            <Image source={require(iconPath+'/check.png')} alt="complete" 
                                                resizeMode="contain"
                                                style={{width: 250, height: 250, marginBottom: 30,}}
                                            />  
                                                      
                                        <Text style={[global.headerL, global.headerFont]} mb="3">Order Confirmed!</Text>  
                                            <Text style={[global.p]} textAlign="center">You will receive an email with your order details within 24 hours.</Text>  
                                        </View>
                                    }
                                    {step == 'error' && 
                                        <View  mt={100} alignItems="center">
                                            <Image source={require(iconPath+'/x.png')} alt="error" 
                                                resizeMode="contain"
                                                style={{width: 250, height: 250, marginBottom: 30,}}
                                            />  
                                                      
                                        <Text style={[global.headerL, global.headerFont]}>There was an error submitting your order</Text>  
                                        </View>
                                    }
                                        
                                </View>
                                
                            }              
                            
                        </View>
                    </ScrollView>
                </View>
                
                <CloseButton color={colors.blue} onPress={() => {
                    setModalVisible(!modalVisible);
                    setStep('shipping');
                }} />
            </Modal>
        ) 
            
        
    }

    
    

    return (  
    <View style={global.outerContainer}>

        
        
        {isProductLoading &&
            <View style={global.loader}>
                <Spinner size="lg" accessibilityLabel="Loading posts" />
            </View>
        } 
              
        {(!isProductLoading && product != null) &&
           <ScrollView keyboardShouldPersistTaps="handled" pb="10" flex="1" refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh}/>}> 
               <TopNavBar navigation={navigation}/>    

                <MemorizedProductDetail product={product} navigation={navigation} openModalChanger={ (value) => { 
                    setOrder((prevState) => ({...prevState, 'properties':value}));
                    setModalVisible(true); 
                } }/>
                
                {renderModal()}
                <View h="200px"></View>
            </ScrollView>
        }
        
        {(!isProductLoading && product == null) &&
            <Text>Unable to locate product</Text>
        }
        
    
    </View>
  
    
  );
};
const styles = StyleSheet.create({
    
    itemContent: {
        marginHorizontal: spacing * 3,
        alignItems: 'center',
        position: 'relative',
    },
    itemText: {
        fontSize: 24,
        position: 'absolute',
        bottom: spacing * 2,
        right: spacing * 2,
        fontWeight: '600',
    },

});


export default SwagDetailScreen;

