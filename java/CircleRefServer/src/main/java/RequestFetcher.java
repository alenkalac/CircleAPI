import com.google.gson.Gson;
import okhttp3.*;

import java.io.IOException;

public class RequestFetcher implements Runnable {

    private int lastID;
    private ProcessRequests pRequsts;
    private OkHttpClient client;

    public static String token = "";
    public static String userID = "";
    public static String accountID = "";

    public RequestFetcher(ProcessRequests pRequsts) {
        System.out.println("STARTING FETCH SERVICE");
        this.client = new OkHttpClient();

        this.pRequsts = pRequsts;

        init();
        lastID = 0; //API CALL TO GET THE LAST PROCESSED ID.


    }
    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    private void init() {

        try {
            Request request = new Request.Builder().url("http://localhost/session").build();
            Response response = client.newCall(request).execute();
            String data = response.body().string();

            Session session = new Gson().fromJson(data, Session.class);

            RequestFetcher.token = session.getToken();
            RequestFetcher.userID = session.getUser();
            RequestFetcher.accountID = session.getAccount();

            System.out.println("SETTING TOKEN TO " + token);

        }catch(IOException e) {
            e.printStackTrace();
        }
    }

    private void doFetch() {
        RequestBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("last_id", String.valueOf(this.lastID))
                .build();

        Request request = new Request.Builder().url("http://localhost/fetch/requests").post(body).build();

        System.out.println("FETCHING WITH LAST_ID " + this.lastID );

        try {
            Response response = client.newCall(request).execute();

            String data = response.body().string();

            FetchResponse[] res = new Gson().fromJson(data, FetchResponse[].class);

            for(FetchResponse r : res ) {
                System.out.println("FETCHED=== " + r.getTransaction_id());
                int currentID = Integer.parseInt(r.getId());
                if(currentID > this.lastID)
                    this.lastID = currentID;
                pRequsts.addRequest(new RequestTransaction(r));
            }

        }catch(IOException e) {
            e.printStackTrace();
        }
    }

    public void run() {
        while(true) {
            doFetch();
            this.sleep(5000);
        }
    }
}
