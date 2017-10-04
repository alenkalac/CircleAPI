import com.google.gson.Gson;
import okhttp3.MultipartBody;
import okhttp3.OkHttpClient;
import okhttp3.RequestBody;
import okhttp3.Response;

import java.io.IOException;

public class RequestTransaction extends Thread {

    private static final Object lock = new Object();

    private FetchResponse transaction;
    private OkHttpClient client;

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

    private void checkTransaction() {
        String url = "http://localhost/transaction";
        RequestBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("tid", transaction.getTransaction_id())
                .addFormDataPart("token", RequestFetcher.token)
                .addFormDataPart("user_id", RequestFetcher.userID)
                .addFormDataPart("account_id", RequestFetcher.accountID)
                .build();

        okhttp3.Request req = new okhttp3.Request.Builder().url(url).post(body).build();

        try {
            Response response = client.newCall(req).execute();

            Transaction tResponse = new Gson().fromJson(response.body().string(), Transaction.class);
            System.out.println(tResponse);
            RequestFetcher.token = tResponse.getData();

            if(tResponse.getError().equals("denied") || tResponse.getError().equals("canceled")) {
                //this.
            }


        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    @Override
    public void run() {
        //while loop
        synchronized (lock) {
            checkTransaction();
        }

        this.sleep(5000);
    }
}
