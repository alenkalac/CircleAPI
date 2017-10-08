import com.google.gson.Gson;
import model.FetchResponse;
import model.TransactionResponse;
import okhttp3.MultipartBody;
import okhttp3.OkHttpClient;
import okhttp3.RequestBody;
import okhttp3.Response;

import java.io.IOException;

public class RequestTransaction extends Thread {

    private static final Object lock = new Object();

    private FetchResponse transaction;
    private OkHttpClient client;
    private boolean running = true;

    public RequestTransaction(FetchResponse transaction) {
        this.transaction = transaction;

        client = new OkHttpClient();
    }

    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    private void requestAnotherTransaction() {
        System.out.println("REQUESTING ANOTHER TRANSACTION");
        String url = "http://localhost/request/transaction";
        RequestBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("tid", transaction.getTransaction_id())
                .build();

        okhttp3.Request req = new okhttp3.Request.Builder()
                .url(url)
                .header("app-token", RequestFetcher.auth_token)
                .post(body)
                .build();
        try {
            Response response = client.newCall(req).execute();
            if(response.code() == 401) {
                RequestFetcher.login();
                requestAnotherTransaction();
            }
        }catch (IOException e) {

        }
    }

    private void checkTransaction() {
        String url = "http://localhost/transaction";
        RequestBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("tid", transaction.getTransaction_id())
                .addFormDataPart("token", RequestFetcher.token)
                .addFormDataPart("user_id", RequestFetcher.userID)
                .addFormDataPart("account_id", RequestFetcher.accountID)
                .build();

        okhttp3.Request req = new okhttp3.Request.Builder()
                .header("app-token", RequestFetcher.auth_token)
                .url(url)
                .post(body)
                .build();

        try {
            Response response = client.newCall(req).execute();
            if(response.code() == 401) {
                RequestFetcher.login();
                return;
            }

            TransactionResponse tResponse = new Gson().fromJson(response.body().string(), TransactionResponse.class);
            System.out.println(tResponse);
            RequestFetcher.token = tResponse.getData();

            if(!tResponse.getError().equals("pending")) {
                this.running = false;
            }
            if(tResponse.getMessage().equals("request")) {
                this.requestAnotherTransaction();
            }


        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    @Override
    public void run() {
       while(running) {
           synchronized (lock) {
               checkTransaction();
           }
           this.sleep(5000);
       }
       System.out.println("Finishing thread " + transaction.getTransaction_id());
    }
}
