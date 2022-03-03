$(document).on('click', '#razor-pay-now', function (e) {
    var total = ($('#amount').val() * 100);
    var merchant_order_id = $('#appointment_id').val();
    var merchant_surl_id = $('#surl').val();
    var merchant_furl_id = $('#furl').val();
    var card_holder_name_id = $('#billing-name').val();
    var merchant_total = total;
    var merchant_amount = $('#amount').val();
    var key_id = $('#key_id').val();;
    var email = $('#billing-email').val();
    var phone = $('#billing-phone').val();
    var currency_code_id = 'INR';
    

    var razorpay_options = {
        key: key_id,
        amount: merchant_total,
        name: 'MyDOC',
        description: 'MyDOC',
        image: 'http://decentinfotech.com/img/core-img/logo.png',
        netbanking: true,
        currency: 'INR',
        prefill: {
            name: card_holder_name_id,
            email: email,
            contact: phone
        },
        notes: {
            soolegal_order_id: merchant_order_id,
        },
        handler: function (transaction) {
            $('#main-js-preloader').show();
            $.ajax({
                url:callBackAfterPayment,
                type: 'post',
                data: {razorpay_payment_id: transaction.razorpay_payment_id, merchant_order_id: merchant_order_id, merchant_surl_id: merchant_surl_id, merchant_furl_id: merchant_furl_id, card_holder_name_id: card_holder_name_id, merchant_total: merchant_total, merchant_amount: merchant_amount, currency_code_id: currency_code_id}, 
                dataType: 'json',
                success: function (res) {
                    if(res.status == 'success'){
                        window.location.href = res.url;
                    }else{
                        swal({
                            title: "Error!",
                            text: res.message,
                            type: "error",
                            timer: 1500
                        });
                    } 
                   
                }
            });
        },
        'modal': {
            'ondismiss': function () {
                $.ajax({
                    url: callBackAfterCancel,
                    type: 'post',
                    data: {merchant_order_id: merchant_order_id, merchant_surl_id: merchant_surl_id, merchant_furl_id: merchant_furl_id, card_holder_name_id: card_holder_name_id, merchant_total: merchant_total, merchant_amount: merchant_amount, currency_code_id: currency_code_id}, 
                    dataType: 'json',
                    success: function (res) {
                        $('#main-js-preloader').hide();
                        if(res.msg){
                            alert(res.msg);
                            return false;
                        } 
                       
                    }
                });
            }
        }
};
// obj        
var objrzpv1 = new Razorpay(razorpay_options);
objrzpv1.open();
    e.preventDefault();
        
});